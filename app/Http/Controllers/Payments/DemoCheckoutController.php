<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Payments\Drivers\DemoDriver;
use App\Payments\Enums\Gateway;
use App\Payments\Enums\PaymentStatus;
use App\Payments\PaymentManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The Demo gateway's pretend hosted checkout page.
 *
 * Registered only where the Demo gateway may run (every environment except
 * production), so in production these routes do not exist at all.
 */
class DemoCheckoutController extends Controller
{
    public function show(Payment $payment): Response
    {
        $this->ensureOpen($payment);

        return response()->view('payments.demo-checkout', ['payment' => $payment]);
    }

    public function store(Request $request, Payment $payment, PaymentManager $payments): RedirectResponse
    {
        $this->ensureOpen($payment);

        $outcome = (string) $request->validate([
            'outcome' => ['required', 'in:approve,decline,cancel'],
        ])['outcome'];

        $driver = $payments->driverFor($payment);
        assert($driver instanceof DemoDriver);

        return redirect()->to($driver->simulateCustomer($payment, $outcome));
    }

    private function ensureOpen(Payment $payment): void
    {
        abort_unless($payment->gateway === Gateway::Demo && $payment->status === PaymentStatus::Pending, 404);
    }
}
