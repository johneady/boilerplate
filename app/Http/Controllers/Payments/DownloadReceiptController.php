<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Payments\ReceiptPdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The receipt as a PDF download, reached by signed URL like the receipt page.
 *
 * Signed for the same reason: most customers pay as guests, and the receipt
 * names them and what they bought. A payment that was never paid has no
 * receipt number and so no PDF -- 404 rather than an empty document.
 */
class DownloadReceiptController extends Controller
{
    public function __invoke(Payment $payment, ReceiptPdf $receipt): StreamedResponse
    {
        abort_unless(ReceiptPdf::availableFor($payment), 404);

        $response = $receipt->download($payment);

        // A receipt names a person and what they bought: kept out of search
        // indexes and shared caches, as the receipt page is.
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
