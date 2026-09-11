<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    @include('partials.head')
</head>
<body class="min-h-screen bg-white antialiased dark:bg-linear-to-b dark:from-neutral-950 dark:to-neutral-900">
    <div class="relative grid h-dvh flex-col items-center justify-center px-8 sm:px-0 lg:max-w-none lg:grid-cols-2 lg:px-0">
        <div class="bg-muted relative hidden h-full flex-col p-10 text-white lg:flex dark:border-e dark:border-neutral-800">
            <img
                src="{{ asset('images/auth/backdrop.svg') }}"
                alt=""
                aria-hidden="true"
                class="absolute inset-0 h-full w-full object-cover"
            />
            <div class="absolute inset-0 bg-linear-to-t from-neutral-950/90 via-neutral-950/45 to-neutral-950/20"></div>
            <a
                href="{{ route('home') }}"
                class="relative z-20 flex items-center text-lg font-medium text-white"
                wire:navigate
            >
                <span class="flex h-10 w-10 items-center justify-center rounded-md">
                    <x-app-logo-icon class="me-2 h-7 fill-current text-white" />
                </span>
                {{ $businessName }}
            </a>

            <div class="relative z-20 mt-auto">
                <blockquote class="space-y-2">
                    <flux:heading size="lg" class="text-white">
                        {{ __('Built for the work that comes next.') }}
                    </flux:heading>
                    <flux:heading class="text-white/90">
                        {{ __('A considered starting point, so the first day looks like the hundredth.') }}
                    </flux:heading>
                </blockquote>
            </div>
        </div>
        <div class="w-full lg:p-8">
            <div class="mx-auto flex w-full flex-col justify-center space-y-6 sm:w-[350px]">
                <a
                    href="{{ route('home') }}"
                    class="z-20 flex flex-col items-center gap-2 font-medium lg:hidden"
                    wire:navigate
                >
                    <span class="flex h-9 w-9 items-center justify-center rounded-md">
                        <x-app-logo-icon class="size-9 fill-current text-black dark:text-white" />
                    </span>

                    <span class="sr-only">{{ $businessName }}</span>
                </a>
                {{ $slot }}
            </div>

            <x-business-footer compact class="mx-auto mt-8 w-full sm:max-w-[350px] text-neutral-500 dark:text-neutral-400" />
        </div>
    </div>

    @persist('toast')
        <flux:toast.group>
            <flux:toast />
        </flux:toast.group>
    @endpersist

    @fluxScripts
</body>
</html>
