<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Ordering\DeliveryFee;
use App\Ordering\Fulfilment;
use App\Ordering\OrderStatus;
use Illuminate\Database\Seeder;

/**
 * A realistic spread of sample orders, so the admin panel and the demo
 * customer's "My orders" have something to show.
 *
 * Demo instances only (DatabaseSeeder gates it), and only into an empty
 * orders table. Runs after the demo accounts, because two orders belong to
 * the demo customer. Built without factories, like every seeder that runs in
 * the deployed image.
 */
class CafeOrdersSeeder extends Seeder
{
    /**
     * Each sample: customer, phone, lines [slug, qty], fulfilment, status,
     * placed this many minutes ago, ready this many minutes from now, notes,
     * and whether it belongs to the demo customer.
     *
     * @var list<array{0: string, 1: string, 2: list<array{0: string, 1: int}>, 3: Fulfilment, 4: OrderStatus, 5: int, 6: int, 7: ?string, 8: bool}>
     */
    private const array SAMPLES = [
        ['Olivia Chen', '(416) 555-0192', [['butter-croissants', 4], ['cinnamon-rolls', 2]], Fulfilment::Pickup, OrderStatus::New, 6, 40, null, false],
        ['Marcus Bell', '(647) 555-0133', [['celebration-layer-cake', 1], ['vanilla-cupcakes', 6]], Fulfilment::Delivery, OrderStatus::New, 14, 120, 'Please write "Happy 40th, Dana!" on the cake.', false],
        ['Emma Walsh', '(416) 555-0107', [['country-sourdough', 1], ['fudge-brownies', 2]], Fulfilment::Pickup, OrderStatus::Preparing, 25, 20, null, true],
        ['Ravi Patel', '(437) 555-0165', [['french-macarons', 2], ['lemon-meringue-tartlets', 4]], Fulfilment::Pickup, OrderStatus::Preparing, 38, 10, 'One of us has a nut allergy — are the tartlets nut-free?', false],
        ['Sofia Morales', '(416) 555-0148', [['chocolate-chip-cookies', 12]], Fulfilment::Delivery, OrderStatus::Ready, 55, -5, 'Office order, reception desk on the 3rd floor.', false],
        ['Jack Thompson', '(647) 555-0171', [['rosemary-focaccia', 2], ['carrot-cake', 2]], Fulfilment::Pickup, OrderStatus::Ready, 70, -10, null, false],
        ['Hannah Kim', '(416) 555-0119', [['lattice-apple-pie', 1]], Fulfilment::Pickup, OrderStatus::Completed, 180, -120, null, false],
        ['Liam O\'Connor', '(437) 555-0102', [['butter-croissants', 6], ['chocolate-fudge-cake', 2]], Fulfilment::Delivery, OrderStatus::Completed, 240, -170, null, false],
        ['Aisha Mohamed', '(416) 555-0184', [['pumpkin-pie', 1], ['cinnamon-rolls', 4]], Fulfilment::Pickup, OrderStatus::Cancelled, 300, -200, 'Sorry, plans changed!', false],
        ['Emma Walsh', '(416) 555-0107', [['celebration-layer-cake', 1]], Fulfilment::Pickup, OrderStatus::Completed, 60 * 24 * 3, -(60 * 24 * 3 - 90), 'Happy Birthday Noah', true],
        ['Noah Williams', '(647) 555-0126', [['country-sourdough', 2], ['fudge-brownies', 4]], Fulfilment::Pickup, OrderStatus::Completed, 60 * 24 + 30, -(60 * 24 - 10), null, false],
        ['Grace Liu', '(416) 555-0139', [['french-macarons', 3]], Fulfilment::Delivery, OrderStatus::Completed, 60 * 26, -(60 * 25), null, false],
    ];

    public function run(): void
    {
        if (Order::query()->exists()) {
            return;
        }

        $products = Product::query()->get()->keyBy('slug');
        $demoCustomer = User::query()->where('email', 'test@example.com')->first();

        foreach (self::SAMPLES as [$name, $phone, $lines, $fulfilment, $status, $placedMinutesAgo, $readyInMinutes, $notes, $isDemoCustomer]) {
            $items = [];

            foreach ($lines as [$slug, $quantity]) {
                $product = $products->get($slug);

                if ($product instanceof Product) {
                    $items[] = [
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'unit_price_cents' => $product->price_cents,
                        'quantity' => $quantity,
                        'line_total_cents' => $product->price_cents * $quantity,
                    ];
                }
            }

            if ($items === []) {
                continue;
            }

            $subtotal = (int) array_sum(array_column($items, 'line_total_cents'));
            $deliveryFee = DeliveryFee::for($fulfilment, $subtotal);
            $placedAt = now()->subMinutes($placedMinutesAgo);

            $order = new Order([
                'user_id' => $isDemoCustomer ? $demoCustomer?->id : null,
                'customer_name' => $isDemoCustomer && $demoCustomer !== null ? $demoCustomer->name : $name,
                'customer_email' => $isDemoCustomer && $demoCustomer !== null ? $demoCustomer->email : strtolower(str_replace([' ', '\''], ['.', ''], $name)).'@example.com',
                'customer_phone' => $phone,
                'fulfilment' => $fulfilment,
                'delivery_address' => $fulfilment === Fulfilment::Delivery ? '88 Carlaw Avenue, Unit 3, Toronto' : null,
                'ready_at' => now()->addMinutes($readyInMinutes)->startOfMinute(),
                'notes' => $notes,
                'status' => $status,
                'subtotal_cents' => $subtotal,
                'delivery_fee_cents' => $deliveryFee,
                'total_cents' => $subtotal + $deliveryFee,
            ]);
            $order->created_at = $placedAt;
            $order->updated_at = $placedAt;
            $order->save();

            // Set here because DatabaseSeeder runs WithoutModelEvents, which
            // skips the model's own created hook that normally assigns it.
            $order->number = Order::numberFor($order->id);
            $order->saveQuietly();

            $order->items()->createMany($items);
        }
    }
}
