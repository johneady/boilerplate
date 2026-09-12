{{--
    Published from the framework solely to brand the header and footer from the
    BusinessName setting instead of config('app.name') -- the same rule every
    other view follows (see .ai/rules/views.md). $businessName reaches these
    templates through the View::composer('*') in AppServiceProvider.

    Only the two message templates are published, not the whole mail theme, so
    the layout, header, footer, button and CSS still track the framework.
--}}
<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.url')">
{{ $businessName }}
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} {{ $businessName }}. {{ __('All rights reserved.') }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
