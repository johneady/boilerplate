<?php

namespace App\Ordering;

use App\Models\Order;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Turn the cart into an order.
 *
 * Prices come from the products in the database at this moment, never from
 * the browser, and each line copies the product's name and price so the order
 * still reads correctly after the menu changes. The order and its lines are
 * written in one transaction, and the cart is emptied only once they exist.
 */
class PlaceOrder
{
    public function __construct(private readonly Cart $cart) {}

    /**
     * @param  array{name: string, email: string, phone: string, fulfilment: Fulfilment, address: ?string, ready_at: CarbonImmutable, notes: ?string}  $details
     *
     * @throws EmptyCart when nothing in the cart can still be ordered
     */
    public function handle(array $details, ?User $customer = null): Order
    {
        $lines = $this->cart->lines();

        if ($lines === []) {
            throw new EmptyCart(__('Your cart is empty.'));
        }

        $subtotal = array_sum(array_map(fn (CartLine $line): int => $line->totalCents(), $lines));
        $deliveryFee = DeliveryFee::for($details['fulfilment'], $subtotal);

        $order = DB::transaction(function () use ($details, $customer, $lines, $subtotal, $deliveryFee): Order {
            $order = Order::create([
                'user_id' => $customer?->id,
                'customer_name' => $details['name'],
                'customer_email' => $details['email'],
                'customer_phone' => $details['phone'],
                'fulfilment' => $details['fulfilment'],
                'delivery_address' => $details['fulfilment'] === Fulfilment::Delivery ? $details['address'] : null,
                'ready_at' => $details['ready_at']->utc(),
                'notes' => $details['notes'],
                'status' => OrderStatus::New,
                'subtotal_cents' => $subtotal,
                'delivery_fee_cents' => $deliveryFee,
                'total_cents' => $subtotal + $deliveryFee,
            ]);

            foreach ($lines as $line) {
                $order->items()->create([
                    'product_id' => $line->product->id,
                    'product_name' => $line->product->name,
                    'unit_price_cents' => $line->product->price_cents,
                    'quantity' => $line->quantity,
                    'line_total_cents' => $line->totalCents(),
                ]);
            }

            return $order;
        });

        $this->cart->clear();

        return $order;
    }
}
