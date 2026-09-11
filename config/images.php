<?php

use Intervention\Image\Drivers\Imagick\Driver;

return [

    /*
    |--------------------------------------------------------------------------
    | Image Processing
    |--------------------------------------------------------------------------
    |
    | Settings for user-supplied images, which are decoded and re-encoded by
    | intervention/image in App\Jobs\ProcessUploadedImage rather than being
    | served as uploaded.
    |
    | Re-encoding is a security control, not only a resizing step. A file can
    | be a valid image and still carry a payload -- PHP in an EXIF comment, a
    | polyglot that a misconfigured server would execute. Decoding to a raster
    | and writing a fresh file discards everything that is not pixels, along
    | with EXIF (which on a phone photo includes GPS coordinates).
    |
    | IMAGE_DRIVER takes the friendly name ("gd" or "imagick") and is mapped to
    | the driver class here, because intervention/image v4 resolves drivers by
    | class name and rejects the bare string.
    |
    */

    'driver' => match (env('IMAGE_DRIVER', 'gd')) {
        'imagick' => Driver::class,
        default => Intervention\Image\Drivers\Gd\Driver::class,
    },

    /*
    |--------------------------------------------------------------------------
    | Disk
    |--------------------------------------------------------------------------
    |
    | Processed images are written here. The "public" disk is served through
    | the storage symlink, which the container entrypoint recreates on every
    | boot because public/ is a fresh image layer each deploy.
    |
    */

    'disk' => env('IMAGE_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Accepted Uploads
    |--------------------------------------------------------------------------
    |
    | Extensions accepted from users, and the ceiling in kilobytes.
    |
    | SVG is deliberately absent and must stay absent. An SVG is a document,
    | not a raster: it may contain <script>, and it is served from the same
    | origin as the application, so accepting one is a stored-XSS vector. The
    | list is enforced with `mimes` rather than Laravel's `image` rule, which
    | admits SVG again whenever `image:allow_svg` is used.
    |
    */

    'accepted_extensions' => ['jpg', 'jpeg', 'png', 'webp'],

    'max_kilobytes' => 5120,

    /*
    |--------------------------------------------------------------------------
    | Conversions
    |--------------------------------------------------------------------------
    |
    | Named output sizes. Each conversion is written as a separate file so a
    | 40px avatar does not ship a 512px image down the wire.
    |
    | "fit" is either "cover" (crop to exactly width x height, used where the
    | layout requires a fixed shape, such as a circular avatar) or "scale"
    | (fit within the box, preserving aspect ratio and never enlarging).
    |
    */

    'conversions' => [

        'avatar' => [
            'thumb' => ['width' => 64, 'height' => 64, 'fit' => 'cover'],
            'full' => ['width' => 512, 'height' => 512, 'fit' => 'cover'],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Encoding
    |--------------------------------------------------------------------------
    |
    | Output format and quality for processed images. WebP is markedly smaller
    | than JPEG at equivalent quality and is supported by every browser this
    | application targets.
    |
    */

    'format' => env('IMAGE_FORMAT', 'webp'),

    'quality' => (int) env('IMAGE_QUALITY', 82),

];
