<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Payments\Actions\ReconcilePayment;
use App\Payments\Enums\PaymentStatus;
use App\Payments\Enums\TransactionSource;
use App\Payments\Exceptions\GatewayException;
use App\Payments\PaymentManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Where a hosted checkout sends the customer back to.
 *
 * The URL is signed (Payment::returnUrl()), because it leads on to the
 * signed receipt. The parameters gateways append on the way back are left out
 * of the check. Nothing here trusts the redirect itself, though: it finishes
 * the checkout where the gateway needs that (PayPal's capture) and then
 * re-reads the payment from the gateway, so the customer lands on a receipt
 * that reflects what actually happened. The webhook does the same
 * independently; the ledger's uniqueness makes the two record one payment.
 */
class PaymentReturnController extends Controller
{
    /**
     * Query parameters the gateways add to the URL they were given.
     */
    public const array GATEWAY_PARAMETERS = ['token', 'PayerID', 'session_id'];

    public function __invoke(Request $request, Payment $payment, PaymentManager $payments, ReconcilePayment $reconcile): RedirectResponse
    {
        abort_unless($request->hasValidSignatureWhileIgnoring(self::GATEWAY_PARAMETERS), 403);

        if ($payment->status === PaymentStatus::Pending && $payment->gateway->isHosted()) {
            try {
                $payments->driverFor($payment)->completeCheckout($payment);
                $reconcile->handle($payment, TransactionSource::Return);
            } catch (GatewayException $e) {
                // The webhook and the scheduled reconciliation will catch up;
                // the customer sees the payment as still processing meanwhile.
                report($e);
            }
        }

        return redirect()->to($payment->receiptUrl());
    }
}
