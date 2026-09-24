<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Where the gateway sends a subscriber who backed out.
 *
 * Nothing is changed: an unfinished subscription checkout is expired by
 * payments:end-subscriptions, or set aside when the customer tries again.
 * A plan change the customer declined to approve is simply left pending
 * until they approve it or choose again.
 */
class SubscriptionCancelledController extends Controller
{
    public function __invoke(Request $request, Subscription $subscription): RedirectResponse
    {
        abort_unless($request->hasValidSignatureWhileIgnoring(SubscriptionReturnController::GATEWAY_PARAMETERS), 403);

        return redirect()->route($subscription->status->hasStarted() ? 'billing.edit' : 'payments.pricing')
            ->with('subscription_cancelled', true);
    }
}
