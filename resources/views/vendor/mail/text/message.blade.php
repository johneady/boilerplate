{{--
    Published for the same reason as the HTML counterpart: the brand shown in
    the header and footer is the BusinessName setting, not config('app.name').
--}}
<x-mail::layout>
    {{-- Header --}}
    <x-slot:header>
        <x-mail::header :url="config('app.url')">
            {{ $businessName }}
        </x-mail::header>
    </x-slot:header>

    {{-- Body --}}
    {{ $slot }}

    {{-- Subcopy --}}
    {{--
        Deliberately omitted from the plain text part. The subcopy exists to
        recover the destination when the HTML button cannot be clicked, but
        this version has no button: the body above already prints the action
        as "Reset Password: <url>" on its own line. Including it repeated the
        same URL a third time, and as unparsed "[url](url)" Markdown, which is
        noisier than the link it was meant to rescue.
    --}}

    {{-- Footer --}}
    <x-slot:footer>
        <x-mail::footer>
            © {{ date('Y') }} {{ $businessName }}. @lang('All rights reserved.')
        </x-mail::footer>
    </x-slot:footer>
</x-mail::layout>
