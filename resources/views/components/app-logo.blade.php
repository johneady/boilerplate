@props([
    'sidebar' => false,
])

@if ($sidebar)
    <flux:sidebar.brand :name="$businessName" {{ $attributes }}>
        <x-slot
            name="logo"
            class="bg-accent-content text-accent-foreground flex aspect-square size-8 items-center justify-center rounded-md"
        >
            <x-app-logo-icon class="text-accent-foreground size-5 fill-current" />
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand :name="$businessName" {{ $attributes }}>
        <x-slot
            name="logo"
            class="bg-accent-content text-accent-foreground flex aspect-square size-8 items-center justify-center rounded-md"
        >
            <x-app-logo-icon class="text-accent-foreground size-5 fill-current" />
        </x-slot>
    </flux:brand>
@endif
