<x-errors.layout
    code="403"
    :title="__('You do not have access to this')"
    :message="__('Your account is signed in, but it is not permitted to view this page. If you think it should be, ask an administrator to check your access.')"
    icon="lock-closed"
    tint="from-amber-500 to-orange-600"
>
    <x-slot:actions>
        @auth
            <flux:button href="/dashboard" variant="primary" icon-trailing="arrow-right">
                {{ __('Back to your dashboard') }}
            </flux:button>
        @else
            <flux:button href="/login" variant="primary" icon-trailing="arrow-right">{{ __('Log in') }}</flux:button>
        @endauth

        <flux:button href="/" variant="ghost">{{ __('Go to the home page') }}</flux:button>
    </x-slot:actions>
</x-errors.layout>
