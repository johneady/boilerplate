{{--
    419 is the page users actually hit in normal use: leaving a form or a
    Livewire page open past the session lifetime expires the CSRF token, and
    the next submit or wire:click lands here. Laravel's default says "Page
    Expired" and nothing else, which reads as a fault rather than as "log in
    again and retry", so the copy explains the cause and the fix.

    Livewire update requests are XHR, and Laravel returns a 419 JSON/HTML
    response that Livewire itself intercepts -- by default it shows its own
    confirmation dialog and reloads the page, which lands the user on the
    login redirect rather than here. So this page is what a full form POST
    with a stale token gets, and what a Livewire request gets when that
    interception is turned off.
--}}
<x-errors.layout
    code="419"
    :title="__('Your session expired')"
    :message="__('This page had been open long enough that its security token expired, so the action was not carried out. Nothing was saved and nothing was lost — log in again and retry it.')"
>
    <x-slot:actions>
        <flux:button href="/login" variant="primary" icon-trailing="arrow-right">{{ __('Log in again') }}</flux:button>

        <flux:button href="/" variant="ghost">{{ __('Go to the home page') }}</flux:button>
    </x-slot:actions>
</x-errors.layout>
