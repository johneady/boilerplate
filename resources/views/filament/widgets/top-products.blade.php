<x-filament-widgets::widget>
    <x-filament::section heading="Top products" description="By revenue, last 90 days">
        <div class="space-y-5">
            @foreach ($this->getProducts() as $product)
                <div>
                    <div class="flex items-baseline justify-between gap-3">
                        <p class="truncate text-sm font-medium text-zinc-950 dark:text-white">{{ $product['name'] }}</p>
                        <p class="shrink-0 text-sm font-semibold text-zinc-950 tabular-nums dark:text-white">
                            {{ $product['revenue'] }}
                        </p>
                    </div>

                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                        <div
                            class="h-full rounded-full transition-[width] duration-500 {{ $product['bar'] }}"
                            style="width: {{ $product['share'] }}%"
                        ></div>
                    </div>

                    <p class="mt-1.5 text-xs text-zinc-500 dark:text-zinc-400">
                        {{ $product['units'] }} &middot; {{ $product['share'] }}% of sales
                    </p>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
