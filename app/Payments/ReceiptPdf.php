<?php

namespace App\Payments;

use App\Models\Media;
use App\Models\Payment;
use App\Payments\Enums\Gateway;
use App\Payments\Enums\GatewayMode;
use App\Settings\SettingKey;
use App\Settings\Settings;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * A payment's receipt as a PDF: the same facts as the receipt page, laid out
 * for printing, filing and expense claims.
 *
 * Offered only once a payment has a receipt number -- that is, once it has
 * been paid. A checkout that failed or was abandoned has nothing to receipt.
 * The PDF is rendered on demand rather than stored: it is small, the payment
 * row it reads never changes, and a stored copy would be one more file to
 * secure and prune.
 */
class ReceiptPdf
{
    /**
     * How long the logo, re-encoded for the PDF, is kept between renders.
     */
    private const int LOGO_CACHE_SECONDS = 86_400;

    public function __construct(private readonly Settings $settings) {}

    /**
     * Whether this payment has a receipt to render.
     */
    public static function availableFor(Payment $payment): bool
    {
        return $payment->receipt_number !== null;
    }

    /**
     * The PDF's bytes.
     */
    public function render(Payment $payment): string
    {
        return Pdf::loadHTML($this->html($payment))
            ->setPaper((string) config('payments.receipt_paper'))
            // Embeds only the glyphs the receipt uses. Without it the whole
            // of DejaVu Sans goes into every file -- over a megabyte on each
            // receipt email -- where a subset is a few dozen kilobytes.
            ->setOption('isFontSubsettingEnabled', true)
            ->output();
    }

    /**
     * The PDF as a download response.
     *
     * One way of answering for both the customer's signed link and the
     * admin panel's action, so the file name header is built -- and
     * escaped -- the same way for both.
     */
    public function download(Payment $payment): StreamedResponse
    {
        $pdf = $this->render($payment);

        return response()->streamDownload(function () use ($pdf): void {
            echo $pdf;
        }, $this->filename($payment), ['Content-Type' => 'application/pdf']);
    }

    /**
     * The receipt as the HTML dompdf lays out.
     *
     * Separate from render() because the PDF's text is compressed: tests and
     * previews read what the receipt says from here.
     */
    public function html(Payment $payment): string
    {
        return view('payments.receipt-pdf', [
            'payment' => $payment,
            'settings' => $this->settings,
            'refunds' => $payment->succeededRefunds(),
            'method' => $this->methodLabel($payment),
            'isTest' => $payment->mode !== GatewayMode::Live,
            'businessName' => $this->settings->businessName(),
            'businessAddress' => $this->settings->string(SettingKey::BusinessAddress),
            'businessPhone' => $this->settings->string(SettingKey::BusinessPhone),
            'businessEmail' => $this->settings->string(SettingKey::BusinessEmail),
            'logo' => $this->logoDataUri(),
            // A manual payment is dated by when the money arrived, as the
            // ledger records it, not by when somebody entered it.
            'paidOn' => $payment->manual_received_on !== null
                ? $this->settings->formatCalendarDate($payment->manual_received_on)
                : $this->settings->formatDate($payment->paid_at),
        ])->render();
    }

    /**
     * The download's file name, e.g. "receipt-R-000123.pdf".
     *
     * Receipt numbers are counted per mode, so a sandbox receipt says so in
     * its name as well as on the page: two files both called
     * receipt-R-000001.pdf must never be a test and a real one.
     */
    public function filename(Payment $payment): string
    {
        return 'receipt-'.$payment->receiptNumber().($payment->mode === GatewayMode::Live ? '' : '-test').'.pdf';
    }

    /**
     * How the customer paid, as the receipt names it.
     */
    private function methodLabel(Payment $payment): string
    {
        if ($payment->gateway === Gateway::Manual && $payment->manual_method !== null) {
            return __($payment->manual_method->label());
        }

        return __($payment->gateway->label());
    }

    /**
     * The uploaded logo as an embedded PNG, or null to print the name alone.
     *
     * Embedded rather than linked: dompdf is kept from fetching remote URLs,
     * which would let whatever the page names be requested from the server.
     * Re-encoded to PNG because the stored conversions are WebP, which
     * dompdf reads only when GD was built with it. Cached per upload, so
     * every receipt does not decode the same image again; a new logo is a
     * new media row and so a new key.
     */
    private function logoDataUri(): ?string
    {
        $media = $this->settings->logoMedia();

        if ($media === null) {
            return null;
        }

        return Cache::remember(
            "receipt-pdf:logo:{$media->id}:{$media->updated_at?->getTimestamp()}",
            self::LOGO_CACHE_SECONDS,
            fn (): ?string => $this->encodeLogo($media),
        );
    }

    /**
     * Read and re-encode the logo's mark conversion.
     *
     * A logo that cannot be read costs the receipt its logo, never the
     * receipt itself. Null is not cached, so a logo still processing is
     * picked up once it is ready.
     */
    private function encodeLogo(Media $media): ?string
    {
        $path = $media->path('mark');

        if ($path === null) {
            return null;
        }

        try {
            $bytes = Storage::disk($media->disk)->get($path);

            if ($bytes === null) {
                return null;
            }

            /** @var string $driver */
            $driver = config('images.driver');

            $png = ImageManager::usingDriver($driver)->decodeBinary($bytes)->encodeUsingFileExtension('png');

            return 'data:image/png;base64,'.base64_encode((string) $png);
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }
}
