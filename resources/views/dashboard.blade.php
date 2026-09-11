<x-layouts::app :title="__('Dashboard')">
    {{--
        The landing page for ordinary signed-in users, NOT an admin screen --
        administration lives in the Filament panel at /admin. Everything linked
        from here is something any authenticated user may do to their own
        account, so nothing on this page is gated on is_admin.
    --}}
    @php
        /**
         * Everything linked from here is something an ordinary authenticated
         * user may do to their OWN account.
         *
         * Declared in the view rather than passed in from a provider: the
         * gradient utilities below are Tailwind class names, and keeping them
         * under resources/views means they sit inside the @source path in
         * resources/css/app.css rather than relying on Tailwind's project-root
         * auto-detection to reach into app/.
         */
        $quickLinks = [
            [
                'title' => __('Profile'),
                'description' => __('Update your name and email address.'),
                'url' => route('profile.edit'),
                'icon' => 'user-circle',
                'tint' => 'from-sky-500 to-indigo-500',
            ],
            [
                'title' => __('Security'),
                'description' => __('Two-factor authentication, passkeys and password.'),
                'url' => route('security.edit'),
                'icon' => 'shield-check',
                'tint' => 'from-emerald-500 to-teal-500',
            ],
            [
                'title' => __('Appearance'),
                'description' => __('Switch between light, dark and system themes.'),
                'url' => route('appearance.edit'),
                'icon' => 'swatch',
                'tint' => 'from-amber-500 to-pink-500',
            ],
        ];
    @endphp

    <div class="flex w-full flex-col gap-6">
        <div class="relative overflow-hidden rounded-xl bg-linear-to-br from-indigo-500 via-purple-500 to-pink-500 p-6 text-white sm:p-8 dark:from-indigo-600 dark:via-purple-600 dark:to-pink-600">
            {{-- Decorative only: aria-hidden so the gradient blobs are not announced. --}}
            <div
                aria-hidden="true"
                class="pointer-events-none absolute -top-16 -right-16 size-56 rounded-full bg-white/15 blur-2xl"
            ></div>
            <div
                aria-hidden="true"
                class="pointer-events-none absolute -bottom-20 -left-10 size-56 rounded-full bg-white/10 blur-2xl"
            ></div>

            <div class="relative">
                {{-- A plain pill rather than flux:badge: the badge's own light
                     palette washes out against the gradient behind it. --}}
                <span class="inline-flex items-center gap-1.5 rounded-full bg-white/20 px-2.5 py-1 text-xs font-medium text-white ring-1 ring-white/30 backdrop-blur-sm ring-inset">
                    <span class="size-1.5 rounded-full bg-lime-300"></span>
                    {{ __('Signed in') }}
                </span>

                <flux:heading size="xl" level="1" class="mt-3 text-white!">
                    {{ __('Welcome back, :name', ['name' => auth()->user()->name]) }}
                </flux:heading>

                <flux:text class="mt-2 max-w-prose text-white/80!">
                    {{ __('This is your :business account. Manage your details and security below.', ['business' => $businessName]) }}
                </flux:text>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($quickLinks as $link)
                <a
                    href="{{ $link['url'] }}"
                    wire:navigate
                    class="group relative flex flex-col gap-3 rounded-xl border border-zinc-200 bg-white p-5 transition hover:-translate-y-0.5 hover:border-zinc-300 hover:shadow-lg focus-visible:outline-2 focus-visible:outline-offset-2 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-zinc-600"
                >
                    <span class="flex size-10 items-center justify-center rounded-lg bg-linear-to-br {{ $link['tint'] }} text-white">
                        <flux:icon :icon="$link['icon']" variant="mini" />
                    </span>

                    <span class="flex-1">
                        <flux:heading size="lg" class="flex items-center gap-1">
                            {{ $link['title'] }}
                            <flux:icon.arrow-right
                                variant="micro"
                                class="opacity-0 transition group-hover:translate-x-0.5 group-hover:opacity-60"
                            />
                        </flux:heading>

                        <flux:text class="mt-1">{{ $link['description'] }}</flux:text>
                    </span>
                </a>
            @endforeach
        </div>
    </div>
</x-layouts::app>
