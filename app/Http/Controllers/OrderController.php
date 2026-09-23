<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Response;

/**
 * The order confirmation a customer lands on after checkout.
 *
 * Reached only through a signed URL (see routes/web.php): customers check out
 * without an account, so the signature is what stops anybody who guesses an
 * order reference from reading someone else's name, email and purchases.
 */
class OrderController extends Controller
{
    /**
     * Show an order's confirmation.
     */
    public function show(Order $order): Response
    {
        $order->load('items.package');

        return response()->view('shop.order', ['order' => $order]);
    }
}
