<?php

namespace App\Livewire;

use App\Bakery\Fulfilment;
use App\Bakery\InquiryMailer;
use App\Bakery\InquiryStatus;
use App\Bakery\Occasion;
use App\Models\MenuItem;
use App\Models\OrderInquiry;
use App\Settings\SettingKey;
use App\Settings\Settings;
use Carbon\CarbonImmutable;
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
 * The public order inquiry form.
 *
 * A request, not a checkout: a home bakery confirms the date and the price by
 * email before anything is baked, so the form collects what the baker needs to
 * quote -- the items from the live menu, the date, pickup or delivery, and the
 * details a cake order always needs -- and shows a running estimate from the
 * menu prices so nobody is surprised by the quote.
 *
 * The earliest date offered follows what is being ordered: each menu item
 * carries its own notice, and the form takes the longest across the lines.
 *
 * Stored first, emailed second, with the contact form's spam defences (see
 * App\Livewire\Contact for the reasoning behind each): a honeypot, a minimum
 * fill time and a per-address rate limit.
 */
class OrderInquiryForm extends Component
{
    private const int MAX_ATTEMPTS_PER_HOUR = 5;

    private const int MINIMUM_FILL_SECONDS = 3;

    /**
     * The order lines, each a menu item id (as the string a <select> sends
     * back, '' until chosen) and a quantity.
     *
     * @var list<array{menuItemId: string, quantity: int|string}>
     */
    public array $lines = [];

    public string $neededOn = '';

    public string $fulfilment = 'pickup';

    public string $deliveryAddress = '';

    public string $occasion = '';

    public string $details = '';

    public string $allergies = '';

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    /**
     * The honeypot, hidden from people. See App\Livewire\Contact.
     */
    public string $website = '';

    #[Locked]
    public int $renderedAt = 0;

    /**
     * The reference of the inquiry just sent, which switches the page to its
     * thank-you state. Locked so the browser cannot claim somebody else's.
     */
    #[Locked]
    public string $reference = '';

    /**
     * Mount the form, preselecting the item a menu card's "Order this" link
     * named. An unknown or unavailable slug is ignored rather than trusted:
     * it arrives from the query string.
     */
    public function mount(): void
    {
        $slug = request()->query('item');
        $preselected = is_string($slug) ? $this->menuItems()->firstWhere('slug', $slug) : null;

        $this->lines = [[
            'menuItemId' => $preselected instanceof MenuItem ? (string) $preselected->id : '',
            'quantity' => 1,
        ]];

        $this->renderedAt = now()->getTimestamp();
    }

    /**
     * The items a customer can order, in menu order.
     *
     * @return Collection<int, MenuItem>
     */
    #[Computed]
    public function menuItems(): Collection
    {
        return MenuItem::query()->available()->ordered()->get();
    }

    /**
     * The days of notice the current lines need: the bakery-wide minimum, or
     * the longest notice among the chosen items when that is more.
     */
    #[Computed]
    public function noticeDays(): int
    {
        $longestItemNotice = (int) $this->chosenItems()->max('notice_days');

        return max((int) config('bakery.minimum_notice_days'), $longestItemNotice);
    }

    /**
     * The first date the form accepts, in the bakery's own timezone -- a
     * customer ordering late in the evening is counting days where the
     * kitchen is, not in UTC.
     */
    #[Computed]
    public function earliestDate(): CarbonImmutable
    {
        return $this->today()->addDays($this->noticeDays());
    }

    /**
     * The last date the form accepts.
     */
    #[Computed]
    public function latestDate(): CarbonImmutable
    {
        return $this->today()->addDays((int) config('bakery.booking_window_days'));
    }

    /**
     * The running estimate from menu prices, in cents.
     */
    #[Computed]
    public function estimateCents(): int
    {
        $items = $this->menuItems()->keyBy('id');

        return (int) collect($this->lines)->sum(function (array $line) use ($items): int {
            $item = $items->get((int) $line['menuItemId']);
            $quantity = max(0, min((int) $line['quantity'], (int) config('bakery.max_quantity')));

            return $item instanceof MenuItem ? $item->price_cents * $quantity : 0;
        });
    }

    /**
     * Add an empty line, up to the configured limit.
     */
    public function addLine(): void
    {
        if (count($this->lines) >= (int) config('bakery.max_lines')) {
            return;
        }

        $this->lines = [...$this->lines, ['menuItemId' => '', 'quantity' => 1]];
    }

    /**
     * Remove a line, always leaving at least one to fill in.
     */
    public function removeLine(int $index): void
    {
        if (count($this->lines) <= 1) {
            return;
        }

        $this->lines = array_values(array_filter(
            $this->lines,
            fn (int $key): bool => $key !== $index,
            ARRAY_FILTER_USE_KEY,
        ));
    }

    /**
     * Validate, store and acknowledge an inquiry.
     */
    public function submit(InquiryMailer $mailer): void
    {
        $this->ensureIsNotRateLimited();

        $validated = $this->validate($this->rules(), $this->messages(), [
            'lines.*.menuItemId' => __('item'),
            'lines.*.quantity' => __('quantity'),
            'neededOn' => __('date'),
            'deliveryAddress' => __('delivery address'),
        ]);

        if ($this->looksAutomated()) {
            $this->finish('');

            return;
        }

        RateLimiter::increment($this->rateLimitKey(), 3600);

        $items = $this->snapshotLines($validated['lines']);

        $inquiry = OrderInquiry::create([
            'reference' => OrderInquiry::newReference(),
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?: null,
            'fulfilment' => Fulfilment::from($validated['fulfilment']),
            'needed_on' => $validated['neededOn'],
            'delivery_address' => $validated['fulfilment'] === Fulfilment::Delivery->value ? $validated['deliveryAddress'] : null,
            'occasion' => filled($validated['occasion']) ? Occasion::from($validated['occasion']) : null,
            'items' => $items,
            'estimated_total_cents' => (int) collect($items)->sum(fn (array $line): int => $line['price_cents'] * $line['quantity']),
            'details' => $validated['details'] ?: null,
            'allergies' => $validated['allergies'] ?: null,
            'status' => InquiryStatus::New,
            'ip_address' => request()->ip(),
            // Truncated to the column width: a header this long is a broken
            // client or a probe, and strict MySQL would reject the insert.
            'user_agent' => mb_substr((string) request()->userAgent(), 0, 255) ?: null,
        ]);

        $mailer->received($inquiry);

        $this->finish($inquiry->reference);
    }

    /**
     * Clear the thank-you state and start a fresh form.
     */
    public function startAnother(): void
    {
        $this->reset(['reference']);
        $this->lines = [['menuItemId' => '', 'quantity' => 1]];
        $this->renderedAt = now()->getTimestamp();
    }

    /**
     * The validation rules for a submission.
     *
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'lines' => ['required', 'array', 'min:1', 'max:'.(int) config('bakery.max_lines')],
            'lines.*.menuItemId' => ['required', Rule::exists('menu_items', 'id')->where('is_available', true)],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:'.(int) config('bakery.max_quantity')],
            'neededOn' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:'.$this->earliestDate()->toDateString(),
                'before_or_equal:'.$this->latestDate()->toDateString(),
            ],
            'fulfilment' => ['required', Rule::enum(Fulfilment::class)],
            'deliveryAddress' => ['nullable', 'required_if:fulfilment,'.Fulfilment::Delivery->value, 'string', 'max:500'],
            'occasion' => ['nullable', Rule::enum(Occasion::class)],
            'details' => ['nullable', 'string', 'max:3000'],
            'allergies' => ['nullable', 'string', 'max:1000'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * Messages that say what to do rather than restating the rule.
     *
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'lines.*.menuItemId.required' => __('Choose an item from the menu, or remove this line.'),
            'lines.*.menuItemId.exists' => __('That item is not available right now. Please choose another.'),
            'neededOn.after_or_equal' => __('This order needs :days days\' notice. The earliest date we can offer is :date.', [
                'days' => $this->noticeDays(),
                'date' => app(Settings::class)->formatDate($this->earliestDate()),
            ]),
            'neededOn.before_or_equal' => __('We take orders up to :days days ahead. Please choose an earlier date.', [
                'days' => (int) config('bakery.booking_window_days'),
            ]),
            'deliveryAddress.required_if' => __('Tell us where to deliver, or choose pickup.'),
        ];
    }

    /**
     * Freeze each line as the customer saw it, merging repeated items.
     *
     * The name, unit and price are copied rather than referenced so a later
     * menu edit cannot change what somebody asked for.
     *
     * @param  array<int, array{menuItemId: string, quantity: int|string}>  $lines
     * @return list<array{menu_item_id: int, name: string, price_unit: string, price_cents: int, quantity: int}>
     */
    private function snapshotLines(array $lines): array
    {
        $items = MenuItem::query()->whereKey(array_column($lines, 'menuItemId'))->get()->keyBy('id');

        return array_values(collect($lines)
            ->groupBy(fn (array $line): int => (int) $line['menuItemId'])
            ->map(function ($group, int $id) use ($items): array {
                /** @var MenuItem $item */
                $item = $items->get($id);

                return [
                    'menu_item_id' => $item->id,
                    'name' => $item->name,
                    'price_unit' => $item->price_unit,
                    'price_cents' => $item->price_cents,
                    'quantity' => min((int) $group->sum('quantity'), (int) config('bakery.max_quantity')),
                ];
            })
            ->all());
    }

    /**
     * The menu items the current lines have chosen.
     *
     * @return Collection<int, MenuItem>
     */
    private function chosenItems(): Collection
    {
        $ids = array_filter(array_map(fn (array $line): int => (int) $line['menuItemId'], $this->lines));

        return $this->menuItems()->whereIn('id', $ids);
    }

    /**
     * Today, in the bakery's display timezone.
     */
    private function today(): CarbonImmutable
    {
        return CarbonImmutable::now(app(Settings::class)->string(SettingKey::Timezone))->startOfDay();
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
            'name' => __('You have sent several order requests recently. Please try again later, or email us.'),
        ]);
    }

    private function rateLimitKey(): string
    {
        return 'order-inquiry:'.request()->ip();
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
     * Clear the form and show the thank-you state.
     *
     * A caught bot gets the same success toast with no reference: telling it
     * which check caught it is how the next version gets past both.
     */
    private function finish(string $reference): void
    {
        $this->reset(['neededOn', 'fulfilment', 'deliveryAddress', 'occasion', 'details', 'allergies', 'name', 'email', 'phone', 'website']);

        $this->lines = [['menuItemId' => '', 'quantity' => 1]];
        $this->renderedAt = now()->getTimestamp();
        $this->reference = $reference;

        Flux::toast(variant: 'success', text: __('Thank you. Your order request has been sent.'));
    }

    /**
     * Render the form inside the public layout. Named explicitly because
     * Livewire's default layout is the signed-in shell -- see Contact::render().
     */
    public function render(): View
    {
        return view('livewire.order-inquiry-form', [
            'fulfilmentOptions' => Fulfilment::cases(),
            'occasionOptions' => Occasion::cases(),
        ])
            ->layout('layouts::public', [
                'title' => __('Order'),
                'description' => __('Request cakes, breads and bakes for pickup or local delivery. We confirm every order by email within a day.'),
            ]);
    }
}
