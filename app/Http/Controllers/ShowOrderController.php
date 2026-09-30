<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\View\View;

/**
 * The page a customer follows their order on.
 *
 * Reached by the signed link from checkout (Order::trackingUrl()), so guests
 * need no account and order numbers cannot be guessed into.
 */
class ShowOrderController extends Controller
{
    public function __invoke(Order $order): View
    {
        return view('orders.show', [
            'order' => $order->load('items'),
        ]);
    }
}
