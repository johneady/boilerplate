<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Payments\Contracts\Payable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Where a hosted checkout sends a customer who backed out.
 *
 * The payment is left pending -- the customer may still go back and finish,
 * and an abandoned one is expired by payments:expire-checkouts -- and they are
 * sent back to the page they started from to try again.
 */
class PaymentCancelledController extends Controller
{
    public function __invoke(Request $request, Payment $payment): RedirectResponse
    {
        // Signed like the return URL, since it can fall back to the receipt.
        abort_unless($request->hasValidSignatureWhileIgnoring(PaymentReturnController::GATEWAY_PARAMETERS), 403);

        $payable = $payment->payable;
        $url = $payable instanceof Payable ? $payable->payableUrl() : null;

        return redirect()
            ->to($url ?? $payment->receiptUrl())
            ->with('payment_cancelled', true);
    }
}
