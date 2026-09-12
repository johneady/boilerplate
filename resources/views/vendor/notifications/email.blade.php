{{--
    Published from the framework solely to sign off with the BusinessName
    setting instead of config('app.name'), matching the header and footer in
    resources/views/vendor/mail. $businessName reaches this template through
    the View::composer('*') in AppServiceProvider.

    Unlike the mail:: templates alongside it, this one is found by Blade's
    ordinary vendor-override lookup and needs no config entry.
--}}
<x-mail::message>
{{-- Greeting --}}
@if (! empty($greeting))
# {{ $greeting }}
@else
@if ($level === 'error')
# @lang('Whoops!')
@else
# @lang('Hello!')
@endif
@endif

{{-- Intro Lines --}}
@foreach ($introLines as $line)
{{ $line }}

@endforeach

{{-- Action Button --}}
@isset($actionText)
<?php
    $color = match ($level) {
        'success', 'error' => $level,
        default => 'primary',
    };
?>
<x-mail::button :url="$actionUrl" :color="$color">
{{ $actionText }}
</x-mail::button>
@endisset

{{-- Outro Lines --}}
@foreach ($outroLines as $line)
{{ $line }}

@endforeach

{{-- Salutation --}}
@if (! empty($salutation))
{{ $salutation }}
@else
@lang('Regards,')<br>
{{ $businessName }}
@endif

{{-- Subcopy --}}
@isset($actionText)
<x-slot:subcopy>
{{-- Shortened from the framework's two-line paragraph. The fallback link
     still earns its place -- security scanners that rewrite hrefs, locked-down
     corporate clients, and reading on a phone to finish on a laptop -- but it
     is recovery text, not content, so it is one muted line rather than a
     sentence that competes with the message above it. --}}
@lang('Button not working? Use this link:') <span class="break-all">[{{ $displayableActionUrl }}]({{ $actionUrl }})</span>
</x-slot:subcopy>
@endisset
</x-mail::message>
