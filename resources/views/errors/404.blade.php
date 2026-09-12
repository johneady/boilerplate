<x-errors.layout
    code="404"
    :title="__('We could not find that page')"
    :message="__('The address may be mistyped, or the page may have been moved or deleted. Nothing is wrong with your account.')"
>
    <x-slot:actions>
        <flux:button
            href="/"
            variant="primary"
            icon-trailing="arrow-right"
        >{{ __('Go to the home page') }}</flux:button>

        @auth
            <flux:button href="/dashboard" variant="ghost">{{ __('Your dashboard') }}</flux:button>
        @endauth
    </x-slot:actions>
</x-errors.layout>
