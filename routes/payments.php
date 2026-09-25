<?php

use App\Auth\DevLoginAccounts;
use App\Http\Controllers\Payments\DemoCheckoutController;
use App\Http\Controllers\Payments\DemoSubscriptionCheckoutController;
use App\Http\Controllers\Payments\PaymentCancelledController;
use App\Http\Controllers\Payments\PaymentReturnController;
use App\Http\Controllers\Payments\PaymentWebhookController;
use App\Http\Controllers\Payments\ShowPaymentController;
use App\Http\Controllers\Payments\SubscriptionCancelledController;
use App\Http\Controllers\Payments\SubscriptionReturnController;
use App\Http\Middleware\EnsurePaymentsEnabled;
use App\Livewire\Payments\PayPaymentLink;
use App\Livewire\Payments\Pricing;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Payments
|--------------------------------------------------------------------------
|
| None is a single path segment, so none can collide with an administrator's
| content page slug.
|
| Switching payments off stops NEW payments: the pay page and the demo
| checkout answer 404 (EnsurePaymentsEnabled). What concerns payments already
| made keeps working -- a customer's emailed receipt link, the return from a
| checkout already open, and the webhooks that complete refunds and holds in
| flight. Those routes need a payment's uuid (and a signature) or a verified
| webhook, so on an installation that never took a payment they lead nowhere.
|
*/

Route::middleware(EnsurePaymentsEnabled::class)->group(function (): void {
    Route::livewire('pay/{paymentLink:token}', PayPaymentLink::class)->name('payments.pay');

    // Public, so the plans can be compared before signing in; subscribing
    // itself asks for a verified account (App\Livewire\Payments\Pricing).
    Route::livewire('pricing', Pricing::class)->name('payments.pricing');

    // The Demo gateway's pretend checkout exists only where the Demo gateway
    // may run -- the same environments that offer the dev login -- so in
    // production these routes are not registered at all. Signed, like a real
    // gateway's checkout URL is unguessable: on a shared staging or demo site,
    // knowing a payment's uuid (printed on its receipt) must not be enough to
    // approve or decline it.
    if (app(DevLoginAccounts::class)->enabled()) {
        Route::middleware('signed')->group(function (): void {
            Route::get('demo-checkout/{payment}', [DemoCheckoutController::class, 'show'])->name('payments.demo.show');
            Route::post('demo-checkout/{payment}', [DemoCheckoutController::class, 'store'])->name('payments.demo.store');
            Route::get('demo-checkout/subscriptions/{subscription}', [DemoSubscriptionCheckoutController::class, 'show'])->name('subscriptions.demo.show');
            Route::post('demo-checkout/subscriptions/{subscription}', [DemoSubscriptionCheckoutController::class, 'store'])->name('subscriptions.demo.store');
        });
    }
});

// Signed (Payment::returnUrl()), checked in the controllers so the parameters
// gateways append on the way back can be ignored.
Route::get('payments/{payment}/return', PaymentReturnController::class)->name('payments.return');
Route::get('payments/{payment}/cancelled', PaymentCancelledController::class)->name('payments.cancelled');

// Signed like the payment ones (Subscription::returnUrl()), and for the same
// reason not behind EnsurePaymentsEnabled: a subscription checkout already
// open finishes even if payments are switched off meanwhile.
Route::get('subscriptions/{subscription}/return', SubscriptionReturnController::class)->name('subscriptions.return');
Route::get('subscriptions/{subscription}/cancelled', SubscriptionCancelledController::class)->name('subscriptions.cancelled');

// Signed: guests pay without an account, so the signature is what keeps a
// receipt private. See Payment::receiptUrl().
Route::get('payments/{payment}', ShowPaymentController::class)
    ->middleware('signed')
    ->name('payments.show');

// Called by the gateways, not a browser: no CSRF token, authenticated by the
// webhook signature instead (PaymentWebhookController), which answers 404 for
// a gateway that was never given a signing secret.
Route::post('webhooks/{gateway}/{mode}', PaymentWebhookController::class)
    ->withoutMiddleware(ValidateCsrfToken::class)
    ->middleware('throttle:payment-webhooks')
    ->name('payments.webhook');
