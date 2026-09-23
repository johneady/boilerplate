<?php

namespace App\Livewire\Shop;

use App\Models\Order;
use App\Models\Package;
use App\Notifications\OrderConfirmed;
use App\Shop\Cart;
use App\Shop\Checkout;
use App\Shop\Money;
use App\Shop\PackageUnavailableException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Throwable;

/**
 * The basket and the one-page checkout beneath it.
 *
 * Checkout is deliberately a single step -- review, name and email, pay --
 * because a licence needs no shipping address, and every extra step is a
 * place a buyer abandons.
 */
class Basket extends Component
{
    public string $name = '';

    public string $email = '';

    public bool $acceptTerms = false;

    /**
     * Prefill the contact details for a signed-in customer.
     */
    public function mount(): void
    {
        $user = auth()->user();

        if ($user !== null) {
            $this->name = $user->name;
            $this->email = $user->email;
        }
    }

    /**
     * The packages in the basket that can still be bought.
     *
     * @return Collection<int, Package>
     */
    #[Computed]
    public function packages(): Collection
    {
        return app(Cart::class)->packages();
    }

    /**
     * The basket total, formatted.
     */
    #[Computed]
    public function total(): string
    {
        return Money::format((int) $this->packages()->sum('price_cents'));
    }

    /**
     * Take a package out of the basket.
     */
    public function remove(int $packageId): void
    {
        app(Cart::class)->remove($packageId);

        unset($this->packages, $this->total);

        $this->dispatch('cart-updated');
    }

    /**
     * Validate the details and place the order.
     *
     * A package that sold out since it was added is taken out of the basket
     * and reported beside the order summary, so the customer can decide
     * whether to go ahead with the rest rather than be charged for it silently.
     */
    public function placeOrder(Checkout $checkout, Cart $cart): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'acceptTerms' => ['accepted'],
        ], [
            'acceptTerms.accepted' => __('Please accept the licence terms to continue.'),
        ]);

        $packages = $this->packages();

        if ($packages->isEmpty()) {
            $this->addError('basket', __('Your basket is empty.'));

            return;
        }

        try {
            $order = $checkout->placeOrder(
                array_values($packages->map(fn (Package $package): int => $package->id)->all()),
                $validated['name'],
                $validated['email'],
                auth()->user(),
            );
        } catch (PackageUnavailableException $exception) {
            $cart->remove($exception->package->id);
            unset($this->packages, $this->total);
            $this->dispatch('cart-updated');

            $this->addError('basket', __(':package has just sold out and was removed from your basket. Please review your order.', [
                'package' => $exception->package->title,
            ]));

            return;
        }

        $cart->clear();

        $this->sendConfirmation($order);

        $this->redirect(URL::signedRoute('orders.show', $order), navigate: true);
    }

    /**
     * Email the customer their confirmation.
     *
     * Any failure is logged and swallowed, the way Contact::notifyBusiness()
     * treats one: the order is placed and the customer is about to land on
     * its confirmation page, so an unreachable mail server must not turn a
     * completed purchase into an error they would retry and pay twice for.
     */
    private function sendConfirmation(Order $order): void
    {
        try {
            Notification::route('mail', $order->customer_email)
                ->notify(new OrderConfirmed($order->load('items')));
        } catch (Throwable $exception) {
            Log::error('Failed to send order confirmation.', [
                'order' => $order->reference,
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Render the basket inside the public layout.
     */
    public function render(): View
    {
        return view('livewire.shop.basket')
            ->layout('layouts::public', ['title' => __('Your basket')]);
    }
}
