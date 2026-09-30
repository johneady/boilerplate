<?php

namespace App\Livewire\Ordering;

use App\Ordering\Cart;
use App\Ordering\CartLine;
use App\Ordering\DeliveryFee;
use App\Ordering\EmptyCart;
use App\Ordering\Fulfilment;
use App\Ordering\PlaceOrder;
use App\Ordering\Price;
use App\Ordering\ReadyTimes;
use App\Settings\SettingKey;
use App\Settings\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The cart and checkout page.
 *
 * The customer adjusts quantities, chooses pickup or delivery and a time, and
 * places the order. Nothing about price is taken from the browser: the totals
 * shown here and the order written by PlaceOrder are both worked out from the
 * products in the database.
 */
class Checkout extends Component
{
    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $fulfilment = 'pickup';

    public string $address = '';

    /** The chosen time, as 'Y-m-d H:i' in the café's timezone. */
    public string $readyAt = '';

    public string $notes = '';

    public function mount(ReadyTimes $readyTimes): void
    {
        if (auth()->check()) {
            $this->name = (string) auth()->user()?->name;
            $this->email = (string) auth()->user()?->email;
        }

        $this->readyAt = ($readyTimes->available()[0] ?? null)?->format('Y-m-d H:i') ?? '';
    }

    /**
     * @return list<CartLine>
     */
    #[Computed]
    public function lines(): array
    {
        return app(Cart::class)->lines();
    }

    #[Computed]
    public function subtotalCents(): int
    {
        return array_sum(array_map(fn (CartLine $line): int => $line->totalCents(), $this->lines()));
    }

    #[Computed]
    public function deliveryFeeCents(): int
    {
        return DeliveryFee::for(Fulfilment::tryFrom($this->fulfilment) ?? Fulfilment::Pickup, $this->subtotalCents());
    }

    /**
     * The time choices, grouped under "Today" and "Tomorrow" for the select.
     *
     * @return Collection<string, list<CarbonImmutable>>
     */
    #[Computed]
    public function readyTimes(): Collection
    {
        return collect(app(ReadyTimes::class)->available())
            ->groupBy(fn (CarbonImmutable $time): string => $time->isSameDay(CarbonImmutable::now($time->timezone)) ? __('Today') : __('Tomorrow'))
            ->map(fn (Collection $times): array => $times->values()->all());
    }

    public function increment(int $productId, Cart $cart): void
    {
        foreach ($this->lines() as $line) {
            if ($line->product->id === $productId) {
                $cart->set($productId, $line->quantity + 1);
            }
        }

        $this->cartChanged();
    }

    public function decrement(int $productId, Cart $cart): void
    {
        foreach ($this->lines() as $line) {
            if ($line->product->id === $productId) {
                $cart->set($productId, $line->quantity - 1);
            }
        }

        $this->cartChanged();
    }

    public function remove(int $productId, Cart $cart): void
    {
        $cart->remove($productId);

        $this->cartChanged();
    }

    public function placeOrder(PlaceOrder $placeOrder, ReadyTimes $readyTimes, Settings $settings): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:32', 'regex:/^[0-9+().\-\s]{7,}$/'],
            'fulfilment' => ['required', Rule::enum(Fulfilment::class)],
            'address' => ['nullable', 'required_if:fulfilment,delivery', 'string', 'max:255'],
            'readyAt' => ['required', 'date_format:Y-m-d H:i'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'phone.regex' => __('Enter a phone number we can call about your order.'),
            'address.required_if' => __('Enter the address to deliver to.'),
        ]);

        $readyAt = CarbonImmutable::createFromFormat('Y-m-d H:i', $validated['readyAt'], $settings->string(SettingKey::Timezone));

        if ($readyAt === null || ! $readyTimes->isAvailable($readyAt)) {
            throw ValidationException::withMessages(['readyAt' => __('That time is no longer available. Please choose another.')]);
        }

        try {
            $order = $placeOrder->handle([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'fulfilment' => Fulfilment::from($validated['fulfilment']),
                'address' => filled($validated['address']) ? $validated['address'] : null,
                'ready_at' => $readyAt,
                'notes' => filled($validated['notes']) ? $validated['notes'] : null,
            ], auth()->user());
        } catch (EmptyCart $e) {
            throw ValidationException::withMessages(['cart' => $e->getMessage()]);
        }

        $this->dispatch('cart-updated');

        $this->redirect($order->trackingUrl());
    }

    public function formatPrice(int $cents): string
    {
        return Price::format($cents);
    }

    public function render(): View
    {
        return view('livewire.ordering.checkout')->layout('layouts::public', ['title' => __('Your order')]);
    }

    private function cartChanged(): void
    {
        unset($this->lines, $this->subtotalCents, $this->deliveryFeeCents);

        $this->dispatch('cart-updated');
    }
}
