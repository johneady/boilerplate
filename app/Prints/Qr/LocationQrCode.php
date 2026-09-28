<?php

namespace App\Prints\Qr;

use App\Models\PrintLocation;
use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\EyeFill;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\Gradient;
use BaconQrCode\Renderer\RendererStyle\GradientType;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * The QR code a location hands to a customer's phone camera.
 *
 * The scan is the front door of the whole branded experience, so the code
 * itself carries the brand: the lab's teal running into its deep harbour blue
 * as a diagonal gradient, on white. Both ends stay dark enough that the code's
 * contrast survives a phone camera at a counter (QR spec wants dark-on-light;
 * a pale gradient endpoint is what makes novelty codes unscannable).
 *
 * Rendered as SVG so one string scales from a shelf sticker to a counter
 * poster without the server owning a rasteriser.
 */
final class LocationQrCode
{
    /**
     * The QR for a location's order URL, as an SVG string.
     *
     * @param  int  $size  Rendered square size in pixels; the SVG scales
     *                     freely, so this only picks the coordinate space.
     */
    public static function forLocation(PrintLocation $location, int $size = 320): string
    {
        $foreground = new Gradient(
            new Rgb(15, 90, 96), // teal-800-ish
            new Rgb(12, 49, 65), // the harbour navy
            // DASPRiD enum, not a PHP enum: the constants are protected and
            // reached through the magic static method of the same name.
            GradientType::DIAGONAL(),
        );

        $renderer = new ImageRenderer(
            new RendererStyle(
                $size,
                margin: 2,
                fill: Fill::withForegroundGradient(
                    new Rgb(255, 255, 255),
                    $foreground,
                    EyeFill::inherit(),
                    EyeFill::inherit(),
                    EyeFill::inherit(),
                ),
            ),
            new SvgImageBackEnd,
        );

        return (new Writer($renderer))->writeString(
            route('photo.location', $location->slug),
        );
    }
}
