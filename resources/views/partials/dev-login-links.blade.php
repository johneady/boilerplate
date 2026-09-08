<div class="rounded-xl border border-dashed border-zinc-300 bg-white/60 p-4 dark:border-zinc-700 dark:bg-zinc-900/30">
    <div class="mb-3 flex items-center gap-2">
        <flux:icon.bolt variant="micro" class="text-amber-600 dark:text-amber-400" />
        <span class="text-xs font-semibold tracking-wide text-zinc-800 uppercase dark:text-zinc-200">
            {{ __('Quick dev login') }}
        </span>
        <span class="ms-auto font-mono text-[0.65rem] text-zinc-500 dark:text-zinc-400"> {{ config('app.env') }} </span>
    </div>

    <div class="flex flex-col gap-2">
        @php
            $devUsers = [
                ['email' => config('first.user.email'), 'name' => config('first.user.name'), 'admin' => true],
                ['email' => 'test@example.com', 'name' => 'Test User', 'admin' => false],
            ];
        @endphp

        @foreach ($devUsers as $devUser)
            <div class="flex items-center gap-3 rounded-lg border border-zinc-200 bg-white px-3 py-2 transition hover:border-zinc-300 hover:bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900/60 dark:hover:border-zinc-700 dark:hover:bg-zinc-900">
                <div class="min-w-0 flex-1">
                    <x-dynamic-component
                        component="login-link"
                        :email="$devUser['email']"
                        :label="$devUser['name']"
                        class="block w-full text-start text-sm font-medium text-zinc-800 focus-visible:outline-2 focus-visible:outline-offset-2 dark:text-zinc-100"
                    />
                    <span class="block truncate font-mono text-[0.7rem] text-zinc-500 dark:text-zinc-400">
                        {{ $devUser['email'] }}
                    </span>
                </div>

                @if ($devUser['admin'])
                    <flux:badge size="sm" color="amber" inset="top bottom">{{ __('Admin panel') }}</flux:badge>
                @else
                    <flux:badge size="sm" color="zinc" inset="top bottom">{{ __('Dashboard') }}</flux:badge>
                @endif
            </div>
        @endforeach
    </div>

    <p class="mt-3 text-[0.7rem] leading-relaxed text-zinc-500 dark:text-zinc-400">
        {{ __('Admins land on the admin panel, everyone else on the dashboard — unless you were redirected here, in which case you continue to where you were headed.') }}
    </p>
</div>
