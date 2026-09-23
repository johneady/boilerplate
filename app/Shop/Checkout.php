<?php

namespace App\Shop;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Package;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Turns a basket into an order, and reverses one on refund.
 *
 * Every stock change happens here, inside a transaction holding row locks on
 * the packages involved. Two customers buying the last licence at the same
 * moment are serialised by the lock: the second reads stock 0 and is refused,
 * rather than both reading 1 and the shop overselling.
 */
class Checkout
{
    /**
     * Place an order for the given packages.
     *
     * Payment is simulated in this demo: the order is written as paid. A real
     * deployment takes payment (Stripe Checkout, say) before this is called,
     * or from the payment provider's webhook, so an order row always means
     * money was taken.
     *
     * @param  list<int>  $packageIds
     *
     * @throws PackageUnavailableException when any package cannot be sold.
     */
    public function placeOrder(array $packageIds, string $customerName, string $customerEmail, ?User $user = null): Order
    {
        if ($packageIds === []) {
            throw new InvalidArgumentException('An order needs at least one package.');
        }

        return DB::transaction(function () use ($packageIds, $customerName, $customerEmail, $user): Order {
            $packages = Package::query()
                ->whereIn('id', $packageIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($packages as $package) {
                if (! $package->isAvailable()) {
                    throw new PackageUnavailableException($package);
                }
            }

            foreach ($packages as $package) {
                if ($package->stock !== null) {
                    $package->decrement('stock');
                }
            }

            $order = Order::create([
                'reference' => Order::newReference(),
                'user_id' => $user?->id,
                'customer_name' => $customerName,
                'customer_email' => $customerEmail,
                'status' => OrderStatus::Paid,
                'total_cents' => (int) $packages->sum('price_cents'),
            ]);

            $order->items()->createMany($packages->map(fn (Package $package): array => [
                'package_id' => $package->id,
                'package_title' => $package->title,
                'price_cents' => $package->price_cents,
            ])->all());

            return $order;
        });
    }

    /**
     * Mark an order's download links as delivered.
     */
    public function fulfil(Order $order): void
    {
        if ($order->status !== OrderStatus::Paid) {
            return;
        }

        $order->update(['status' => OrderStatus::Fulfilled, 'fulfilled_at' => now()]);
    }

    /**
     * Refund an order and put its licences back into stock.
     *
     * Refunding twice would return the licences twice, so an order already
     * refunded is left alone.
     */
    public function refund(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($order->status === OrderStatus::Refunded) {
                return;
            }

            $order->items()->with('package')->get()->each(function (OrderItem $item): void {
                if ($item->package?->stock !== null) {
                    $item->package->increment('stock');
                }
            });

            $order->update(['status' => OrderStatus::Refunded]);
        });

        $order->refresh();
    }
}
