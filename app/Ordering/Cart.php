<?php

namespace App\Ordering;

use App\Models\Product;
use Illuminate\Contracts\Session\Session;

/**
 * The visitor's shopping cart, kept in their session.
 *
 * Only product ids and quantities are stored. Prices and names are always
 * read fresh from the database, so a price changed in the admin panel is
 * what the customer pays, and a product switched off drops out of the cart
 * instead of being ordered.
 */
class Cart
{
    private const string SESSION_KEY = 'cart';

    /** The most of one product a single online order may contain. */
    public const int MAX_QUANTITY = 24;

    public function __construct(private readonly Session $session) {}

    public function add(int $productId, int $quantity = 1): void
    {
        $items = $this->items();

        $this->set($productId, ($items[$productId] ?? 0) + $quantity);
    }

    /**
     * Set a product's quantity, removing it at zero.
     */
    public function set(int $productId, int $quantity): void
    {
        $items = $this->items();

        if ($quantity <= 0) {
            unset($items[$productId]);
        } else {
            $items[$productId] = min($quantity, self::MAX_QUANTITY);
        }

        $this->session->put(self::SESSION_KEY, $items);
    }

    public function remove(int $productId): void
    {
        $this->set($productId, 0);
    }

    public function clear(): void
    {
        $this->session->forget(self::SESSION_KEY);
    }

    /**
     * The cart's lines, for products that can still be ordered, in menu order.
     *
     * @return list<CartLine>
     */
    public function lines(): array
    {
        $items = $this->items();

        if ($items === []) {
            return [];
        }

        $products = Product::query()
            ->available()
            ->whereKey(array_keys($items))
            ->ordered()
            ->get();

        $lines = [];

        foreach ($products as $product) {
            $lines[] = new CartLine($product, $items[$product->id]);
        }

        return $lines;
    }

    /**
     * How many items are in the cart, counting quantities.
     */
    public function count(): int
    {
        return array_sum(array_map(fn (CartLine $line): int => $line->quantity, $this->lines()));
    }

    public function subtotalCents(): int
    {
        return array_sum(array_map(fn (CartLine $line): int => $line->totalCents(), $this->lines()));
    }

    public function isEmpty(): bool
    {
        return $this->lines() === [];
    }

    /**
     * The raw product id => quantity map held in the session.
     *
     * @return array<int, int>
     */
    private function items(): array
    {
        $items = $this->session->get(self::SESSION_KEY, []);

        return is_array($items) ? $items : [];
    }
}
