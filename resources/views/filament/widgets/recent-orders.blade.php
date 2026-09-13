@php
    $statusStyles = [
        'Paid' => 'bg-emerald-100 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-400/10 dark:text-emerald-300 dark:ring-emerald-400/20',
        'Pending' => 'bg-amber-100 text-amber-700 ring-amber-600/20 dark:bg-amber-400/10 dark:text-amber-300 dark:ring-amber-400/20',
        'Shipped' => 'bg-sky-100 text-sky-700 ring-sky-600/20 dark:bg-sky-400/10 dark:text-sky-300 dark:ring-sky-400/20',
        'Refunded' => 'bg-zinc-100 text-zinc-600 ring-zinc-500/20 dark:bg-zinc-400/10 dark:text-zinc-300 dark:ring-zinc-400/20',
    ];

    $avatarGradients = [
        'from-blue-600 to-sky-500',
        'from-emerald-600 to-teal-500',
        'from-amber-600 to-orange-500',
        'from-violet-600 to-purple-500',
        'from-rose-600 to-pink-500',
        'from-cyan-600 to-blue-500',
        'from-indigo-600 to-violet-500',
    ];
@endphp

<x-filament-widgets::widget>
    <x-filament::section heading="Recent orders" description="The latest orders placed on the site">
        <div class="-my-1 divide-y divide-zinc-200 dark:divide-zinc-800">
            @foreach ($this->getOrders() as $index => $order)
                <div class="flex items-center gap-3 py-2.5">
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-gradient-to-br text-xs font-semibold text-white shadow-sm {{ $avatarGradients[$index % count($avatarGradients)] }}">
                        {{ $order['initials'] }}
                    </span>

                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-zinc-950 dark:text-white">
                            {{ $order['customer'] }}
                        </p>
                        <p class="truncate text-xs text-zinc-500 dark:text-zinc-400">
                            {{ $order['product'] }} &middot; {{ $order['placed_at'] }}
                        </p>
                    </div>

                    <span class="hidden shrink-0 rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset sm:inline {{ $statusStyles[$order['status']] }}">
                        {{ $order['status'] }}
                    </span>

                    <span class="w-24 shrink-0 text-right text-sm font-semibold text-zinc-950 tabular-nums dark:text-white">
                        {{ $order['amount'] }}
                    </span>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
