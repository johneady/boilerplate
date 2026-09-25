<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Payments\Drivers\DemoDriver;
use App\Payments\Enums\Gateway;
use App\Payments\Enums\SubscriptionStatus;
use App\Payments\PaymentManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The Demo gateway's pretend subscription checkout page.
 *
 * Registered only where the Demo gateway may run, like DemoCheckoutController.
 */
class DemoSubscriptionCheckoutController extends Controller
{
    public function show(Subscription $subscription): Response
    {
        $this->ensureOpen($subscription);

        return response()->view('payments.demo-subscription-checkout', [
            'subscription' => $subscription->load(['plan', 'price']),
        ]);
    }

    public function store(Request $request, Subscription $subscription, PaymentManager $payments): RedirectResponse
    {
        $this->ensureOpen($subscription);

        $outcome = (string) $request->validate([
            'outcome' => ['required', 'in:approve,cancel'],
        ])['outcome'];

        $driver = $payments->subscriptionDriver($subscription->gateway, $subscription->mode);
        assert($driver instanceof DemoDriver);

        return redirect()->to($driver->simulateSubscriber($subscription, $outcome));
    }

    private function ensureOpen(Subscription $subscription): void
    {
        abort_unless($subscription->gateway === Gateway::Demo && $subscription->status === SubscriptionStatus::Incomplete, 404);
    }
}
