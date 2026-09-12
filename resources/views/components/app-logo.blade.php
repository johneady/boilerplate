@props([
    'sidebar' => false,
])

{{--
    The brand lockup: the mark beside the business name.

    The mark carries its own colour now -- the uploaded logo, or the bundled
    gradient SVG -- so the slot no longer paints an accent tile behind it,
    which would fight both. It keeps the square box and rounding so the two
    branches occupy the same space in the sidebar.
--}}
@if ($sidebar)
    <flux:sidebar.brand :name="$businessName" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center overflow-hidden rounded-md">
            <x-app-logo-icon class="size-8" />
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand :name="$businessName" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center overflow-hidden rounded-md">
            <x-app-logo-icon class="size-8" />
        </x-slot>
    </flux:brand>
@endif
