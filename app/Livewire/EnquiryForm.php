<?php

namespace App\Livewire;

use App\Models\Enquiry;
use App\Models\Vehicle;
use App\Voltiva\DrivingNeed;
use App\Voltiva\EnquiryMailer;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The one enquiry form, embedded wherever the site asks for an enquiry: the
 * foot of the home page, every car page, and "Register your interest".
 *
 * Opened from a car page it arrives with that car selected, and each
 * placement passes its own `source`, so the CRM record says which car and
 * which page the customer came from without them typing either.
 *
 * Stored first, emailed second, with the same spam defences as the contact
 * form (App\Livewire\Contact explains each): a honeypot, a minimum fill time
 * and a per-address rate limit.
 */
class EnquiryForm extends Component
{
    private const int MAX_ATTEMPTS_PER_HOUR = 5;

    private const int MINIMUM_FILL_SECONDS = 3;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    /**
     * The chosen car's id as a string ('' for "not sure yet"), because that
     * is what a <select> sends back.
     */
    public string $vehicleId = '';

    public string $location = '';

    /**
     * @var list<string>
     */
    public array $drivingNeeds = [];

    public bool $financeInterest = false;

    public bool $registrationInterest = true;

    public string $message = '';

    /**
     * The honeypot, hidden from people. See App\Livewire\Contact.
     */
    public string $website = '';

    #[Locked]
    public string $source = 'register';

    #[Locked]
    public int $renderedAt = 0;

    public bool $sent = false;

    /**
     * Mount the form, preselecting the car it was opened for.
     *
     * An unknown slug or source is ignored rather than trusted: both arrive
     * from a query string on the Register Your Interest page.
     */
    public function mount(?string $vehicle = null, string $source = 'register'): void
    {
        $this->source = array_key_exists($source, Enquiry::SOURCES) ? $source : 'register';

        if (filled($vehicle)) {
            $match = $this->vehicles()->firstWhere('slug', $vehicle);
            $this->vehicleId = $match instanceof Vehicle ? (string) $match->id : '';
        }

        $this->renderedAt = now()->getTimestamp();
    }

    /**
     * The cars a customer can choose between.
     *
     * @return Collection<int, Vehicle>
     */
    #[Computed]
    public function vehicles(): Collection
    {
        return Vehicle::query()->published()->ordered()->get(['id', 'slug', 'name', 'category']);
    }

    /**
     * Validate, store and acknowledge an enquiry.
     */
    public function submit(EnquiryMailer $mailer): void
    {
        $this->ensureIsNotRateLimited();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'vehicleId' => ['nullable', Rule::exists('vehicles', 'id')->where('is_published', true)],
            'location' => ['nullable', 'string', 'max:255'],
            'drivingNeeds' => ['array'],
            'drivingNeeds.*' => [Rule::enum(DrivingNeed::class)],
            'financeInterest' => ['boolean'],
            'registrationInterest' => ['boolean'],
            'message' => ['nullable', 'string', 'max:5000'],
        ], attributes: [
            'vehicleId' => __('car'),
            'drivingNeeds.*' => __('driving requirement'),
        ]);

        if ($this->looksAutomated()) {
            $this->finish();

            return;
        }

        RateLimiter::increment($this->rateLimitKey(), 3600);

        $vehicle = filled($validated['vehicleId']) ? Vehicle::query()->whereKey($validated['vehicleId'])->first() : null;

        $enquiry = Enquiry::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?: null,
            'vehicle_id' => $vehicle?->id,
            'vehicle_name' => $vehicle?->name,
            'location' => $validated['location'] ?: null,
            'driving_needs' => array_values(array_unique($validated['drivingNeeds'])),
            'finance_interest' => $validated['financeInterest'],
            'registration_interest' => $validated['registrationInterest'],
            'message' => $validated['message'] ?: null,
            'source' => $this->source,
            'locale' => app()->getLocale(),
            'ip_address' => request()->ip(),
            'user_agent' => mb_substr((string) request()->userAgent(), 0, 255) ?: null,
        ]);

        $mailer->received($enquiry);

        $this->finish();
    }

    /**
     * Refuse a submission from an address that has sent too many already.
     */
    private function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->rateLimitKey(), self::MAX_ATTEMPTS_PER_HOUR)) {
            return;
        }

        throw ValidationException::withMessages([
            'message' => __('You have sent several enquiries recently. Please try again later or call us.'),
        ]);
    }

    private function rateLimitKey(): string
    {
        return 'enquiry-form:'.request()->ip();
    }

    /**
     * Whether the submission shows the marks of an automated one. Timed with
     * now(), never time() -- see App\Livewire\Contact::looksAutomated().
     */
    private function looksAutomated(): bool
    {
        if ($this->website !== '') {
            return true;
        }

        return $this->renderedAt === 0
            || (now()->getTimestamp() - $this->renderedAt) < self::MINIMUM_FILL_SECONDS;
    }

    /**
     * Clear the customer's details and show the thank-you state. The chosen
     * car stays selected: it came from the page, not from the customer.
     */
    private function finish(): void
    {
        $this->reset(['name', 'email', 'phone', 'location', 'drivingNeeds', 'financeInterest', 'message', 'website']);

        $this->renderedAt = now()->getTimestamp();
        $this->sent = true;

        Flux::toast(variant: 'success', text: __('Thank you. We will be in touch within one working day.'));
    }

    public function render(): View
    {
        return view('livewire.enquiry-form', [
            'drivingNeedOptions' => DrivingNeed::cases(),
        ]);
    }
}
