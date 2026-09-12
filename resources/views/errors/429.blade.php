<x-errors.layout
    code="429"
    :title="__('Too many attempts')"
    :message="__('You have made too many requests in a short period, so this one was turned away. Wait a minute or so and try again — the limit clears on its own.')"
>
    <x-slot:actions>
        {{--
            A reload rather than a link: the user wants the same URL again once
            the limiter's window has passed, and the throttled route is often
            not one of the two below.

            url()->full(), not current(): current() drops the query string, so
            retrying a filtered or paginated page would silently send the user
            somewhere other than where they were turned away.

            This is a GET link, so it retries a throttled GET. A throttled POST
            (a login attempt, for one) cannot be replayed from here -- the user
            goes back to the form, which is what the second button is for.
        --}}
        <flux:button href="{{ url()->full() }}" variant="primary" icon="arrow-path">{{ __('Try again') }}</flux:button>

        <flux:button href="/" variant="ghost">{{ __('Go to the home page') }}</flux:button>
    </x-slot:actions>
</x-errors.layout>
