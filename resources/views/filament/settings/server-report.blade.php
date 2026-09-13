{{--
    The read-only server report on the Server tab.

    Like the Diagnostics tab, everything here is presented as findings
    rather than fields: there is nothing to submit, because none of it is
    the application's to change -- it is the machine the application was
    handed. A dash means this host does not expose that detail, which is
    the expected shape of a shared host rather than an error.

    Colours come from x-filament components' color props rather than
    palette utilities, so this view stays styled by the panel theme
    without depending on which utility classes the last CSS build saw.
--}}
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="text-sm">
            <p class="font-medium text-gray-950 dark:text-white">{{ __('Server report') }}</p>
            <p class="text-gray-500 dark:text-gray-400">
                {{ __('What the machine handling this request is running, read live. A dash means this host does not expose that detail.') }}
            </p>
        </div>

        <x-filament::badge color="gray" icon="heroicon-o-server">
            {{ $report['sections'][0]['rows']['Operating system'] }}
        </x-filament::badge>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        @foreach ($report['sections'] as $section)
            <section class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
                <h3 class="text-sm font-semibold text-zinc-950 dark:text-white">{{ __($section['title']) }}</h3>

                <dl class="mt-3 space-y-2">
                    @foreach ($section['rows'] as $label => $value)
                        <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-0.5 text-sm">
                            <dt class="text-gray-500 dark:text-gray-400">{{ __($label) }}</dt>

                            @if ($label === 'Used' && ($section['usedPercent'] ?? null) !== null)
                                <dd>
                                    <x-filament::badge :color="$section['usedPercent'] >= 90 ? 'danger' : ($section['usedPercent'] >= 75 ? 'warning' : 'success')">
                                        {{ $value }}
                                    </x-filament::badge>
                                </dd>
                            @else
                                <dd class="font-medium text-gray-950 dark:text-white">{{ $value ?? '—' }}</dd>
                            @endif
                        </div>
                    @endforeach
                </dl>

                @if (($section['usedPercent'] ?? null) !== null)
                    {{-- The bar repeats the Used badge for feel rather than information; a width
                         style keeps it honest on any theme build. --}}
                    <div class="mt-4 h-1.5 overflow-hidden rounded-full bg-zinc-100 dark:bg-white/10">
                        <div
                            class="h-full rounded-full {{ $section['usedPercent'] >= 90 ? 'bg-red-500' : ($section['usedPercent'] >= 75 ? 'bg-amber-500' : 'bg-emerald-500') }}"
                            style="width: {{ min($section['usedPercent'], 100) }}%"
                            role="progressbar"
                            aria-valuemin="0"
                            aria-valuemax="100"
                            aria-valuenow="{{ $section['usedPercent'] }}"
                            aria-label="{{ __('Disk in use') }}"
                        ></div>
                    </div>
                @endif
            </section>
        @endforeach
    </div>

    <details class="group">
        <summary class="cursor-pointer text-sm font-medium text-gray-500 hover:text-gray-950 dark:text-gray-400 dark:hover:text-white">
            {{ trans_choice('{1} :count loaded extension|[2,*] :count loaded extensions', count($report['extensions']), ['count' => count($report['extensions'])]) }}
        </summary>

        <ul class="mt-3 flex flex-wrap gap-2">
            @foreach ($report['extensions'] as $extension)
                <li class="rounded-md bg-zinc-50 px-2 py-1 font-mono text-xs text-zinc-600 ring-1 ring-zinc-950/5 dark:bg-white/5 dark:text-zinc-300 dark:ring-white/10">
                    {{ $extension }}
                </li>
            @endforeach
        </ul>
    </details>
</div>
