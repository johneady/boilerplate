<?php

namespace App\Livewire\Shop;

use App\Models\Package;
use App\Shop\Cart;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The product page for one package.
 *
 * An inactive package is a 404 for the public but renders for anybody who may
 * edit it, the way PageController treats a draft page, so the panel's "view"
 * link works before a package goes on sale.
 */
class ShowPackage extends Component
{
    public Package $package;

    /**
     * Mount the component.
     */
    public function mount(Package $package): void
    {
        abort_unless(
            $package->is_active || auth()->user()?->can('update', $package),
            404,
        );

        $this->package = $package;
    }

    /**
     * Put the package in the basket.
     *
     * Availability is re-read rather than trusted from when the page loaded:
     * the last licence may have sold while the visitor was reading.
     */
    public function addToCart(): void
    {
        $this->package->refresh();

        if (! $this->package->isAvailable()) {
            Flux::toast(variant: 'danger', text: __('Sorry, this package is no longer available.'));

            return;
        }

        app(Cart::class)->add($this->package);

        $this->dispatch('cart-updated');

        Flux::toast(variant: 'success', text: __('Added to your basket.'));
    }

    /**
     * Put the package in the basket and go straight to checkout.
     */
    public function buyNow(): void
    {
        $this->addToCart();

        if (app(Cart::class)->has($this->package->id)) {
            $this->redirectRoute('cart', navigate: true);
        }
    }

    /**
     * Whether the package is already in the basket.
     */
    #[Computed]
    public function inCart(): bool
    {
        return app(Cart::class)->has($this->package->id);
    }

    /**
     * Up to three other packages to suggest, from the same region first.
     *
     * @return Collection<int, Package>
     */
    #[Computed]
    public function related(): Collection
    {
        return Package::query()
            ->active()
            ->whereKeyNot($this->package->id)
            ->orderByRaw('case when region = ? then 0 else 1 end', [$this->package->region->value])
            ->ordered()
            ->limit(3)
            ->get();
    }

    /**
     * Render the product page inside the public layout.
     */
    public function render(): View
    {
        return view('livewire.shop.show-package')
            ->layout('layouts::public', [
                'title' => $this->package->title,
                'description' => $this->package->summary,
            ]);
    }
}
