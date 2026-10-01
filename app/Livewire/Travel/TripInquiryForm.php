<?php

namespace App\Livewire\Travel;

use App\Models\Destination;
use App\Models\Tour;
use App\Models\TourDeparture;
use App\Models\TripInquiry;
use App\Notifications\TripInquiryAcknowledged;
use App\Notifications\TripInquiryReceived;
use App\Settings\SettingKey;
use App\Settings\Settings;
use App\Travel\TripQuote;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Throwable;

/**
 * The booking request form: a seat on a scheduled departure when given a tour,
 * a tailor-made trip when not.
 *
 * The quote updates live as the party changes, using the same TripQuote the
 * stored request is priced with -- what the traveller sees is what the agency
 * receives. Nothing is held or charged: a request becomes a booking only when
 * staff confirm it (App\Travel\BookingWorkflow).
 *
 * Spam handling follows App\Livewire\Contact: a honeypot, a minimum fill time
 * and a per-address limit, with the automated rejections reported as success.
 */
class TripInquiryForm extends Component
{
    private const int MAX_REQUESTS_PER_HOUR = 5;

    private const int MINIMUM_FILL_SECONDS = 3;

    #[Locked]
    public ?int $tourId = null;

    public ?int $departureId = null;

    public ?int $destinationId = null;

    public string $travelMonth = '';

    public int $adults = 2;

    /**
     * Not named `children`: a form control with that name shadows the form
     * element's own `children` property in the DOM, which breaks Livewire's
     * morph of the whole form.
     */
    public int $childCount = 0;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $message = '';

    public string $website = '';

    public int $renderedAt = 0;

    /**
     * The reference of the request just sent, which swaps the form for a
     * confirmation panel.
     */
    public ?string $sentReference = null;

    public function mount(?Tour $tour = null, ?int $departure = null, ?string $destination = null): void
    {
        $this->tourId = $tour?->id;
        $this->renderedAt = now()->getTimestamp();

        if ($tour !== null) {
            $departures = $this->departures();
            $this->departureId = $departure !== null && $departures->contains('id', $departure)
                ? $departure
                : $departures->first()?->id;
        } elseif ($destination !== null) {
            $this->destinationId = Destination::query()->where('slug', $destination)->value('id');
        }

        if (($user = auth()->user()) !== null) {
            $this->name = $user->name;
            $this->email = $user->email;
        }
    }

    /**
     * Pick a departure from the dates table elsewhere on the tour page.
     */
    #[On('select-departure')]
    public function selectDeparture(int $id): void
    {
        if ($this->departures()->contains('id', $id)) {
            $this->departureId = $id;
            $this->resetValidation('departureId');
        }
    }

    #[Computed]
    public function tour(): ?Tour
    {
        return $this->tourId !== null ? Tour::query()->with('destination')->find($this->tourId) : null;
    }

    /**
     * @return Collection<int, TourDeparture>
     */
    #[Computed]
    public function departures(): Collection
    {
        $tour = $this->tour();

        if ($tour === null) {
            return new Collection;
        }

        return $tour->bookableDepartures()->get()->each->setRelation('tour', $tour);
    }

    #[Computed]
    public function departure(): ?TourDeparture
    {
        return $this->departures()->firstWhere('id', $this->departureId);
    }

    /**
     * @return Collection<int, Destination>
     */
    #[Computed]
    public function destinations(): Collection
    {
        return Destination::query()->ordered()->get(['id', 'name', 'country']);
    }

    /**
     * The live price for the party as entered, or null when it cannot be
     * priced yet (a tailor-made trip, or numbers out of range mid-edit).
     */
    #[Computed]
    public function quote(): ?TripQuote
    {
        $tour = $this->tour();

        if ($tour === null || $this->adults < 1 || $this->childCount < 0) {
            return null;
        }

        return TripQuote::for($tour, $this->departure(), $this->adults, $this->childCount);
    }

    /**
     * The next twelve months, for the tailor-made "when" select.
     *
     * @return list<string>
     */
    #[Computed]
    public function months(): array
    {
        $months = [];
        $month = now()->startOfMonth();

        for ($i = 1; $i <= 12; $i++) {
            $months[] = $month->addMonths($i)->format('F Y');
        }

        return $months;
    }

    public function submit(): void
    {
        if (RateLimiter::tooManyAttempts($this->rateLimitKey(), self::MAX_REQUESTS_PER_HOUR)) {
            throw ValidationException::withMessages([
                'message' => __('You have sent several requests recently. Please call us instead, or try again later.'),
            ]);
        }

        $validated = $this->validate($this->rules());

        if ($this->looksAutomated()) {
            $this->finish('WL-'.strtoupper(substr(md5((string) microtime(true)), 0, 6)));

            return;
        }

        $departure = $this->departure();

        if ($departure !== null && ! $departure->canSeat($this->adults + $this->childCount)) {
            throw ValidationException::withMessages([
                'departureId' => trans_choice('Only :count seat is left on this departure.|Only :count seats are left on this departure.', $departure->seatsLeft()),
            ]);
        }

        RateLimiter::increment($this->rateLimitKey(), 3600);

        $inquiry = TripInquiry::create([
            'user_id' => auth()->id(),
            'tour_id' => $this->tourId,
            'tour_departure_id' => $departure?->id,
            'destination_id' => $this->tour() !== null ? $this->tour()->destination_id : ($validated['destinationId'] ?? null),
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?: null,
            'adults' => $validated['adults'],
            'children' => $validated['childCount'],
            'travel_month' => $departure === null ? ($validated['travelMonth'] ?: null) : null,
            'quoted_total_cents' => $this->quote()?->totalCents(),
            'message' => $validated['message'] ?: null,
            'ip_address' => request()->ip(),
        ]);

        $this->notify($inquiry);
        $this->finish($inquiry->reference);
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $maxParty = (int) config('travel.max_party_size', 8);
        $isTour = $this->tourId !== null;

        return [
            'departureId' => $isTour && $this->departures()->isNotEmpty()
                ? ['required', 'integer', Rule::in($this->departures()->modelKeys())]
                : ['nullable'],
            'destinationId' => $isTour ? ['nullable'] : ['nullable', 'integer', Rule::exists('destinations', 'id')],
            'travelMonth' => ['nullable', 'string', 'max:40'],
            'adults' => ['required', 'integer', 'min:1', 'max:'.$maxParty],
            'childCount' => ['required', 'integer', 'min:0', 'max:'.($maxParty - max(1, $this->adults))],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'message' => [$isTour ? 'nullable' : 'required', 'string', 'max:3000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'departureId' => __('departure date'),
            'destinationId' => __('destination'),
            'childCount' => __('children'),
            'message' => $this->tourId !== null ? __('notes') : __('trip details'),
        ];
    }

    public function render(): View
    {
        return view('livewire.travel.trip-inquiry-form');
    }

    private function looksAutomated(): bool
    {
        return $this->website !== ''
            || $this->renderedAt === 0
            || (now()->getTimestamp() - $this->renderedAt) < self::MINIMUM_FILL_SECONDS;
    }

    private function rateLimitKey(): string
    {
        return 'trip-inquiry:'.request()->ip();
    }

    /**
     * Alert the agency and acknowledge the traveller. The request is already
     * stored, so a failure here is logged rather than shown to the visitor.
     */
    private function notify(TripInquiry $inquiry): void
    {
        try {
            $agency = app(Settings::class)->string(SettingKey::BusinessEmail);

            if ($agency !== '') {
                Notification::route('mail', $agency)->notify(new TripInquiryReceived($inquiry));
            }

            Notification::route('mail', $inquiry->email)->notify(new TripInquiryAcknowledged($inquiry));
        } catch (Throwable $exception) {
            Log::error('Failed to send trip inquiry notifications.', [
                'inquiry_id' => $inquiry->id,
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    private function finish(string $reference): void
    {
        $this->sentReference = $reference;
        $this->reset(['phone', 'message', 'website']);
        $this->renderedAt = now()->getTimestamp();
    }
}
