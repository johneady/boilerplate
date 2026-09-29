<x-layouts::public :title="__('Open stats')" :description="__('Live follower and usage figures for the :business community.', ['business' => $businessName])">
    @php
        $settings = app(\App\Settings\Settings::class);
        $maxViews = max(1, ...array_values($dailyViews));
        $growthValues = array_values($growth);
        $growthMin = min($growthValues);
        $growthRange = max(1, max($growthValues) - $growthMin);
        $growthPoints = collect($growthValues)->map(fn (int $value, int $index): string => round($index * (600 / max(1, count($growthValues) - 1)), 1).','.round(150 - (($value - $growthMin) / $growthRange) * 130, 1))->implode(' ');
    @endphp

    <main class="flex-1 py-10">
        <div class="max-w-2xl">
            <flux:badge size="sm" color="fuchsia" inset="top bottom">{{ __('Updated live') }}</flux:badge>
            <h1 class="mt-4 font-display text-5xl font-semibold tracking-tight">{{ __('Open stats') }}</h1>
            <p class="mt-3 text-neutral-600 dark:text-neutral-400">
                {{ __('How many people follow :business and how much the database is used, shared openly for partners, brands and the community.', ['business' => $businessName]) }}
            </p>
        </div>

        <dl class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                ['label' => __('Followers'), 'value' => $members, 'note' => __('+:count in the last 30 days', ['count' => number_format($newMembers)])],
                ['label' => __('Page views, last 30 days'), 'value' => $monthViews, 'note' => __('Across every perfume page')],
                ['label' => __('Perfumes followed'), 'value' => $follows, 'note' => __('Follows placed by members')],
                ['label' => __('Perfumes in the database'), 'value' => $perfumes, 'note' => $lastImport ? __('Refreshed :date', ['date' => $settings->formatDate($lastImport->finished_at)]) : __('Not refreshed yet')],
            ] as $stat)
                <div class="rounded-2xl border border-plum-100 bg-white/80 p-5 dark:border-plum-900/60 dark:bg-plum-950/40">
                    <dt class="text-sm text-neutral-600 dark:text-neutral-400">{{ $stat['label'] }}</dt>
                    <dd class="mt-1 font-display text-5xl font-semibold [font-variant-numeric:lining-nums] text-neutral-900 dark:text-white">{{ number_format($stat['value']) }}</dd>
                    <dd class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">{{ $stat['note'] }}</dd>
                </div>
            @endforeach
        </dl>

        <div class="mt-8 grid gap-6 lg:grid-cols-2">
            <section class="rounded-2xl border border-plum-100 bg-white/80 p-6 dark:border-plum-900/60 dark:bg-plum-950/40" aria-labelledby="views-heading">
                <h2 id="views-heading" class="font-semibold">{{ __('Daily page views') }}</h2>
                <p class="text-xs text-neutral-500 dark:text-neutral-400">{{ __('Last 30 days') }}</p>

                <div class="mt-6 flex h-40 items-end gap-0.5" role="img" aria-label="{{ __('Bar chart of daily page views for the last 30 days') }}">
                    @foreach ($dailyViews as $date => $views)
                        <div class="group relative flex h-full flex-1 items-end">
                            <div class="w-full rounded-t bg-plum-500 transition group-hover:bg-plum-700 dark:bg-plum-400 dark:group-hover:bg-plum-200" style="height: {{ max(2, round($views / $maxViews * 100)) }}%"></div>
                            <div class="pointer-events-none absolute bottom-full left-1/2 z-10 mb-1 hidden -translate-x-1/2 rounded-md bg-neutral-900 px-2 py-1 text-xs whitespace-nowrap text-white group-hover:block dark:bg-white dark:text-neutral-900">
                                {{ $settings->formatDate(\Carbon\CarbonImmutable::parse($date)) }}: {{ number_format($views) }}
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-2 flex justify-between text-[11px] text-neutral-500 dark:text-neutral-400">
                    <span>{{ $settings->formatDate(\Carbon\CarbonImmutable::parse(array_key_first($dailyViews))) }}</span>
                    <span>{{ __('Today') }}</span>
                </div>
            </section>

            <section class="rounded-2xl border border-plum-100 bg-white/80 p-6 dark:border-plum-900/60 dark:bg-plum-950/40" aria-labelledby="growth-heading">
                <h2 id="growth-heading" class="font-semibold">{{ __('Follower growth') }}</h2>
                <p class="text-xs text-neutral-500 dark:text-neutral-400">{{ __('Total followers, last 12 weeks') }}</p>

                <svg viewBox="-8 -8 616 176" class="mt-6 h-40 w-full overflow-visible" role="img" aria-label="{{ __('Line chart of total followers over the last 12 weeks') }}">
                    <line x1="0" y1="150" x2="600" y2="150" class="stroke-neutral-200 dark:stroke-neutral-800" stroke-width="1" />
                    <polyline points="{{ $growthPoints }}" fill="none" class="stroke-plum-600 dark:stroke-plum-300" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" />
                    @foreach (explode(' ', $growthPoints) as $index => $point)
                        @php([$x, $y] = explode(',', $point))
                        <circle cx="{{ $x }}" cy="{{ $y }}" r="5" class="fill-plum-600 stroke-white dark:fill-plum-300 dark:stroke-plum-950" stroke-width="2">
                            <title>{{ __('Week ending :date: :count followers', ['date' => $settings->formatDate(\Carbon\CarbonImmutable::parse(array_keys($growth)[$index])), 'count' => number_format($growthValues[$index])]) }}</title>
                        </circle>
                    @endforeach
                </svg>
                <div class="mt-2 flex justify-between text-[11px] text-neutral-500 dark:text-neutral-400">
                    <span>{{ number_format($growthValues[0]) }}</span>
                    <span>{{ number_format(end($growthValues)) }}</span>
                </div>
            </section>
        </div>

        <section class="mt-8 rounded-2xl border border-plum-100 bg-white/80 p-6 dark:border-plum-900/60 dark:bg-plum-950/40" aria-labelledby="top-heading">
            <h2 id="top-heading" class="font-semibold">{{ __('Most followed perfumes') }}</h2>

            <ol class="mt-4 divide-y divide-plum-100 dark:divide-plum-900/60">
                @foreach ($mostFollowed as $perfume)
                    <li class="flex items-center gap-4 py-3">
                        <span class="w-6 text-right font-display text-xl text-plum-500">{{ $loop->iteration }}</span>
                        <a href="{{ route('perfumes.show', $perfume) }}" class="min-w-0 flex-1 hover:text-plum-700 dark:hover:text-plum-300" wire:navigate>
                            <span class="font-medium">{{ $perfume->name }}</span>
                            <span class="text-sm text-neutral-500 dark:text-neutral-400">· {{ $perfume->brand->name }}</span>
                        </a>
                        <span class="text-sm text-neutral-600 tabular-nums dark:text-neutral-300">{{ number_format($perfume->followers_count) }}</span>
                    </li>
                @endforeach
            </ol>
        </section>
    </main>
</x-layouts::public>
