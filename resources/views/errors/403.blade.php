{{--
    A 403 reaches two quite different people, so the copy branches.

    A GUEST usually hit a page that needs an account: telling them "your
    account is not permitted" is simply wrong, and telling them to ask an
    administrator sends them down the wrong path when signing in would have
    solved it. A SIGNED-IN user has already proved who they are, so for them
    the fix really is a permissions change somebody else has to make.

    auth()->check() rather than the @auth directive because the value is
    needed in the props above the slot, not only in the markup. It is safe on
    this page: resolving the guard reads the session, not the settings table,
    so it does not reintroduce the database dependency the 500 page avoids --
    and a 403 is only ever reached through a working session in the first
    place.
--}}
@php
    $isSignedIn = auth()->check();
@endphp

<x-errors.layout
    code="403"
    :title="$isSignedIn ? __('You do not have access to this') : __('You need to sign in for this')"
    :message="$isSignedIn
        ? __('Your account is signed in, but it is not permitted to view this page. If you think it should be, ask an administrator to check your access.')
        : __('This page is not public, and you are not signed in. Logging in may be all that is needed — if you are already signed in elsewhere, this browser does not know it.')"
>
    <x-slot:actions>
        <flux:button href="/" variant="ghost">{{ __('Go to the home page') }}</flux:button>
    </x-slot:actions>
</x-errors.layout>
