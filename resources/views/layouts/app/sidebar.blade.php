<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    @include('partials.head')
</head>
<body class="min-h-screen bg-white dark:bg-blue-950">
    {{--
        The signed-in shell is blue-themed. The accent tokens in
        resources/css/app.css carry the current nav item, brand mark and focus
        rings; the classes here tint the sidebar surface itself, which Flux
        leaves neutral.
    --}}
    <flux:sidebar
        sticky
        collapsible="mobile"
        class="border-e border-blue-100 bg-blue-50/70 dark:border-blue-900 dark:bg-blue-950"
    >
        <flux:sidebar.header>
            <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
            <flux:sidebar.collapse class="lg:hidden" />
        </flux:sidebar.header>

        <flux:sidebar.nav>
            <flux:sidebar.group :heading="__('Platform')" class="grid">
                <flux:sidebar.item
                    icon="home"
                    :href="route('dashboard')"
                    :current="request()->routeIs('dashboard')"
                    class="hover:bg-blue-500/10 hover:text-blue-700 data-current:border-blue-200! data-current:bg-white dark:hover:bg-blue-400/15 dark:hover:text-blue-100 dark:data-current:border-blue-700! dark:data-current:bg-blue-500/25"
                    wire:navigate
                >
                    {{ __('Dashboard') }}
                </flux:sidebar.item>
            </flux:sidebar.group>
        </flux:sidebar.nav>

        <flux:spacer />

        <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
    </flux:sidebar>

    <!-- Mobile User Menu -->
    <flux:header class="border-b border-blue-100 bg-blue-50/70 lg:hidden dark:border-blue-900 dark:bg-blue-950">
        <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

        <flux:spacer />

        <flux:dropdown position="top" align="end">
            <flux:profile
                :initials="auth()->user()->initials()"
                icon-trailing="chevron-down"
                class="hover:bg-blue-500/10! dark:hover:bg-blue-400/10!"
            />

            {{-- Mirrors x-desktop-user-menu; keep the two in step. --}}
            <flux:menu class="border-blue-100! bg-blue-50/95! dark:border-blue-800! dark:bg-blue-900!">
                <flux:menu.radio.group>
                    <div class="p-0 text-sm font-normal">
                        <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                            <flux:avatar :name="auth()->user()->name" :initials="auth()->user()->initials()" />

                            <div class="grid flex-1 text-start text-sm leading-tight">
                                <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                            </div>
                        </div>
                    </div>
                </flux:menu.radio.group>

                <div class="-mx-[.3125rem] my-[.3125rem] h-px">
                    <flux:separator class="bg-blue-200! dark:bg-blue-700!" />
                </div>

                <flux:menu.radio.group>
                    <flux:menu.item
                        :href="route('profile.edit')"
                        icon="cog"
                        class="data-active:bg-blue-500/10! data-active:text-blue-700! dark:data-active:bg-blue-400/20! dark:data-active:text-blue-100!"
                        wire:navigate
                    >
                        {{ __('Settings') }}
                    </flux:menu.item>
                </flux:menu.radio.group>

                <div class="-mx-[.3125rem] my-[.3125rem] h-px">
                    <flux:separator class="bg-blue-200! dark:bg-blue-700!" />
                </div>

                <form method="POST" action="{{ route('logout') }}" class="w-full">
                    @csrf
                    <flux:menu.item
                        as="button"
                        type="submit"
                        icon="arrow-right-start-on-rectangle"
                        class="w-full cursor-pointer data-active:bg-blue-500/10! data-active:text-blue-700! dark:data-active:bg-blue-400/20! dark:data-active:text-blue-100!"
                        data-test="logout-button"
                    >
                        {{ __('Log out') }}
                    </flux:menu.item>
                </form>
            </flux:menu>
        </flux:dropdown>
    </flux:header>

    {{ $slot }}

    @persist('toast')
        <flux:toast.group>
            <flux:toast />
        </flux:toast.group>
    @endpersist

    @fluxScripts
</body>
</html>
