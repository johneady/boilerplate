<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Payments\Contracts\Payable;
use App\Payments\Enums\RefundStatus;
use Illuminate\Http\Response;

/**
 * The customer's receipt and payment status page.
 *
 * Reached only through a signed URL (the route's `signed` middleware): most
 * customers pay as guests, so the signature is what keeps one customer's name,
 * email and purchase from anyone else holding a guessed or leaked uuid.
 */
class ShowPaymentController extends Controller
{
    public function __invoke(Payment $payment): Response
    {
        $payable = $payment->payable;

        return response()
            ->view('payments.show', [
                'payment' => $payment,
                'refunds' => $payment->refunds()->where('status', RefundStatus::Succeeded->value)->orderBy('id')->get(),
                'retryUrl' => $payable instanceof Payable && $payable->acceptsPayments() ? $payable->payableUrl() : null,
            ])
            // A receipt names a person and what they bought: kept out of search
            // indexes and out of shared caches whatever the site-wide settings.
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Cache-Control', 'private, no-store');
    }
}
