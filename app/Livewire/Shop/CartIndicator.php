<?php

namespace App\Livewire\Shop;

use App\Shop\Cart;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The basket link in the public header, with a count of what is in it.
 *
 * A component of its own so adding to the basket on a product page updates the
 * header without a reload: the page dispatches cart-updated and this re-renders.
 */
class CartIndicator extends Component
{
    /**
     * Re-render with the new count.
     */
    #[On('cart-updated')]
    public function refreshCount(): void {}

    public function render(): View
    {
        return view('livewire.shop.cart-indicator', [
            'count' => app(Cart::class)->count(),
        ]);
    }
}
