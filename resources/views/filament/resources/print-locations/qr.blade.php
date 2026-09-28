{{--
    The printable QR sign for one counter, shown in the panel's QR modal.

    The code is the counter's order URL in the lab's brand colours (see
    App\Prints\Qr\LocationQrCode), framed with everything a counter sign
    needs: what scanning does, and the URL as text for the camera that will
    not scan.
--}}
@php($url = route('photo.location', $location->slug))

<div class="flex flex-col items-center gap-4 py-2">
    <div class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700">
        {!! $location->qrCodeSvg() !!}
    </div>

    <p class="text-center text-sm text-gray-500">{{ __('print-locations.qr.url_note', ['url' => $url]) }}</p>
</div>
