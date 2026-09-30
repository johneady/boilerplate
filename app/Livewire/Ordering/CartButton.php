<?php

namespace App\Livewire\Ordering;

use App\Ordering\Cart;
use App\Ordering\Price;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The cart link in the site header, showing how many items are in the cart.
 *
 * Refreshes itself whenever another component dispatches `cart-updated`.
 */
class CartButton extends Component
{
    #[On('cart-updated')]
    public function refreshCart(): void
    {
        // Re-rendering is all that is needed; the cart is read in render().
    }

    public function render(Cart $cart): View
    {
        return view('livewire.ordering.cart-button', [
            'count' => $cart->count(),
            'subtotal' => Price::format($cart->subtotalCents()),
        ]);
    }
}
