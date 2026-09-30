<?php

namespace App\Livewire\Ordering;

use App\Models\Product;
use App\Ordering\Cart;
use App\Ordering\ProductCategory;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The home page: the café's menu, with an "Add to cart" button on every
 * product.
 *
 * The category chips filter in the browser (Alpine), since the whole menu is
 * a couple of dozen products; only adding to the cart goes to the server.
 */
class Menu extends Component
{
    /**
     * The available products, grouped into menu sections in menu order.
     *
     * @return Collection<int, array{category: ProductCategory, products: Collection<int, Product>}>
     */
    #[Computed]
    public function sections(): Collection
    {
        $products = Product::query()->available()->ordered()->get()->groupBy(
            fn (Product $product): string => $product->category->value,
        );

        return collect(ProductCategory::cases())
            ->filter(fn (ProductCategory $category): bool => $products->has($category->value))
            ->map(fn (ProductCategory $category): array => [
                'category' => $category,
                'products' => $products->get($category->value),
            ])
            ->values();
    }

    /**
     * Up to three featured products for the hero.
     *
     * @return Collection<int, Product>
     */
    #[Computed]
    public function featured(): Collection
    {
        return Product::query()->available()->where('is_featured', true)->ordered()->limit(3)->get();
    }

    public function addToCart(int $productId, Cart $cart): void
    {
        $product = Product::query()->available()->find($productId);

        if ($product === null) {
            Flux::toast(__('Sorry, that item has just sold out.'), variant: 'danger');

            return;
        }

        $cart->add($product->id);

        $this->dispatch('cart-updated');

        Flux::toast(__(':product added to your cart.', ['product' => $product->name]), variant: 'success');
    }

    public function render(): View
    {
        return view('livewire.ordering.menu')->layout('layouts::public');
    }
}
