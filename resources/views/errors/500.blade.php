{{--
    Rendered when the application itself failed, which very often means the
    database is unreachable -- see the note in errors/layout.blade.php about
    why these pages do not extend the app or auth layouts. Nothing here may
    read a setting or the database.

    Deliberately no "try again" button: a 500 is not usually transient from
    the user's side, and inviting a retry on a broken write is worse than
    sending them somewhere that works. It also says nothing about the cause --
    the operator reads the exception in the logs, the user cannot act on it.
--}}
<x-errors.layout
    code="500"
    :title="__('Something went wrong on our end')"
    :message="__('This is a fault on our side, not anything you did. It has been logged for us to look at. Please try again in a few minutes.')"
>
    <x-slot:actions>
        <flux:button
            href="/"
            variant="primary"
            icon-trailing="arrow-right"
        >{{ __('Go to the home page') }}</flux:button>
    </x-slot:actions>
</x-errors.layout>
