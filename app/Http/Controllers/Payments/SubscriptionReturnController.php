<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Payments\Actions\ReconcileSubscription;
use App\Payments\Enums\TransactionSource;
use App\Payments\Exceptions\GatewayException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Where the gateway sends a subscriber back to, after subscribing or
 * approving a plan change.
 *
 * Like PaymentReturnController, nothing here trusts the redirect: the
 * subscription is re-read from the gateway and the subscriber lands on their
 * billing page showing what the gateway actually did.
 */
class SubscriptionReturnController extends Controller
{
    /**
     * Query parameters the gateways add to the URL they were given.
     */
    public const array GATEWAY_PARAMETERS = ['session_id', 'subscription_id', 'ba_token', 'token'];

    public function __invoke(Request $request, Subscription $subscription, ReconcileSubscription $reconcile): RedirectResponse
    {
        abort_unless($request->hasValidSignatureWhileIgnoring(self::GATEWAY_PARAMETERS), 403);

        try {
            $reconcile->handle($subscription, TransactionSource::Return);
        } catch (GatewayException $e) {
            // The webhook and the scheduled reconciliation will catch up;
            // the billing page shows it as still starting meanwhile.
            report($e);
        }

        return redirect()->route('billing.edit')->with('subscription_returned', true);
    }
}
