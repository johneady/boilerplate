<?php

namespace App\Shop;

use App\Models\Package;
use Illuminate\Contracts\Session\Session;
use Illuminate\Database\Eloquent\Collection;

/**
 * The visitor's basket, held in the session.
 *
 * A package is a licence rather than a physical item, so the basket holds each
 * package at most once and there is no quantity to manage. Only ids are stored:
 * prices and availability are always read fresh, so a price changed or a
 * package sold out while it sat in somebody's basket is what they see at
 * checkout, not what it was when they added it.
 */
class Cart
{
    private const string SESSION_KEY = 'cart.packages';

    public function __construct(private readonly Session $session) {}

    /**
     * Put a package in the basket. Adding one already there changes nothing.
     */
    public function add(Package $package): void
    {
        if ($this->has($package->id)) {
            return;
        }

        $this->session->put(self::SESSION_KEY, [...$this->ids(), $package->id]);
    }

    /**
     * Take a package out of the basket.
     */
    public function remove(int $packageId): void
    {
        $this->session->put(
            self::SESSION_KEY,
            array_values(array_filter($this->ids(), fn (int $id): bool => $id !== $packageId)),
        );
    }

    /**
     * Whether the package is in the basket.
     */
    public function has(int $packageId): bool
    {
        return in_array($packageId, $this->ids(), true);
    }

    /**
     * The ids of the packages in the basket, in the order they were added.
     *
     * @return list<int>
     */
    public function ids(): array
    {
        $ids = $this->session->get(self::SESSION_KEY, []);

        return is_array($ids) ? array_values(array_map(intval(...), $ids)) : [];
    }

    /**
     * How many packages are in the basket.
     */
    public function count(): int
    {
        return count($this->ids());
    }

    /**
     * Empty the basket.
     */
    public function clear(): void
    {
        $this->session->forget(self::SESSION_KEY);
    }

    /**
     * The packages in the basket that are still listed in the storefront.
     *
     * A package deactivated since it was added simply drops out; checkout
     * re-checks stock under a lock, so this is for display, not a guarantee.
     *
     * @return Collection<int, Package>
     */
    public function packages(): Collection
    {
        $ids = $this->ids();

        if ($ids === []) {
            return new Collection;
        }

        return Package::query()
            ->active()
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(fn (Package $package): int|false => array_search($package->id, $ids, true))
            ->values();
    }
}
