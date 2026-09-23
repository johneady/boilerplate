{{--
    Calculator 2, for active agents: take-home under their current split and
    royalties, side by side with the company's plan from config/calculators.php.

    As on the income planner, the page renders empty result slots and
    resources/js/calculators.js fills them. The headline's three wordings are
    handed to the script as data attributes, so the copy stays in the
    translation files; :amount is left in them for the script to fill.
--}}
@php
    $calculators = config('calculators');
    $comparison = $calculators['split_comparison'];
    $plan = $calculators['company_plan'];

    $card = 'rounded-2xl border border-neutral-200 bg-white/80 shadow-sm dark:border-neutral-800 dark:bg-neutral-900/60';
    $inputClasses = 'block w-full rounded-lg border border-neutral-300 bg-white py-2.5 text-lg font-semibold tabular-nums shadow-xs focus:border-teal-600 focus:ring-2 focus:ring-teal-600/30 focus:outline-none user-invalid:border-red-500 dark:border-neutral-700 dark:bg-neutral-950 dark:focus:border-teal-400';

    $fields = [
        [
            'name' => 'sales_volume', 'label' => __('Annual sales volume'), 'help' => __('The combined sale prices of the homes you closed in a year.'),
            'value' => $comparison['default_sales_volume'], 'step' => '10000', 'inputmode' => 'numeric', 'prefix' => $calculators['currency_symbol'], 'max' => null,
        ],
        [
            'name' => 'commission_percent', 'label' => __('Average commission'), 'help' => __('Your average rate per side, before any split.'),
            'value' => $comparison['default_commission_percent'], 'step' => '0.05', 'inputmode' => 'decimal', 'suffix' => '%', 'max' => 100,
        ],
        [
            'name' => 'split_percent', 'label' => __('Your current split'), 'help' => __('The share you keep. Enter 70 for a 70/30 split.'),
            'value' => $comparison['default_split_percent'], 'step' => '1', 'inputmode' => 'decimal', 'suffix' => '%', 'max' => 100,
        ],
        [
            'name' => 'royalty_percent', 'label' => __('Royalties and franchise fees'), 'help' => __('Taken off the top of each commission. Enter 0 if none.'),
            'value' => $comparison['default_royalty_percent'], 'step' => '0.5', 'inputmode' => 'decimal', 'suffix' => '%', 'max' => 100,
        ],
    ];

    $rows = [
        ['key' => 'royalty', 'label' => __('Royalties')],
        ['key' => 'companySplit', 'label' => __('Company split')],
    ];

    if ((float) $plan['annual_fees'] > 0) {
        $rows[] = ['key' => 'fees', 'label' => __('Brokerage fees')];
    }
@endphp

<x-layouts::public
    :title="__('Split comparison')"
    :description="__('Compare your take-home pay under your current brokerage split and royalties with the :business plan. Free, no sign-up.', ['business' => $businessName])"
>
    <main class="flex-1 pt-2 pb-12 sm:pt-6">
        <x-calculators.switcher current="calculators.split-comparison" class="sm:max-w-sm" />

        <div class="mt-6 max-w-2xl sm:mt-8">
            <p class="text-sm font-medium text-teal-700 dark:text-teal-300">{{ __('For active agents') }}</p>
            <h1 class="mt-1 text-3xl font-semibold tracking-tight text-balance sm:text-4xl">
                {{ __('What would you keep with :business?', ['business' => $businessName]) }}
            </h1>
            <x-calculators.plan-summary class="mt-3 text-neutral-600 dark:text-neutral-400" />
        </div>

        <div class="mt-8 grid gap-6 lg:grid-cols-[22rem_minmax(0,1fr)] lg:items-start">
            <form
                data-calculator="split-comparison"
                class="{{ $card }} space-y-5 p-5 sm:p-6 lg:sticky lg:top-6"
                novalidate
            >
                @foreach ($fields as $field)
                    <div>
                        <label for="{{ $field['name'] }}" class="block font-medium">{{ $field['label'] }}</label>
                        <p id="{{ $field['name'] }}-help" class="mt-0.5 text-sm text-neutral-600 dark:text-neutral-400">
                            {{ $field['help'] }}
                        </p>
                        <div class="relative mt-2">
                            @isset($field['prefix'])
                                <span
                                    class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-neutral-500 dark:text-neutral-400"
                                    aria-hidden="true"
                                >{{ $field['prefix'] }}</span>
                            @endisset
                            <input
                                id="{{ $field['name'] }}"
                                name="{{ $field['name'] }}"
                                type="number"
                                inputmode="{{ $field['inputmode'] }}"
                                min="0"
                                @if ($field['max'] !== null) max="{{ $field['max'] }}" @endif
                                step="{{ $field['step'] }}"
                                value="{{ $field['value'] }}"
                                aria-describedby="{{ $field['name'] }}-help"
                                @class([$inputClasses, 'pl-8' => isset($field['prefix']), 'pl-3' => ! isset($field['prefix']), 'pr-9' => isset($field['suffix']), 'pr-3' => ! isset($field['suffix'])])
                            />
                            @isset($field['suffix'])
                                <span
                                    class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-neutral-500 dark:text-neutral-400"
                                    aria-hidden="true"
                                >{{ $field['suffix'] }}</span>
                            @endisset
                        </div>
                    </div>
                @endforeach
            </form>

            <section
                data-results="split-comparison"
                data-empty
                aria-labelledby="comparison-heading"
                class="group/results space-y-6 transition-opacity data-empty:opacity-60"
            >
                <div class="{{ $card }} p-5 sm:p-6">
                    <h2 id="comparison-heading" class="text-sm font-medium text-neutral-600 dark:text-neutral-400">
                        {{ __('Your result') }}
                    </h2>
                    <p
                        data-headline
                        aria-live="polite"
                        data-more-text="{{ __('You could take home :amount more a year with :business.', ['business' => $businessName]) }}"
                        data-less-text="{{ __('Your current plan pays :amount more a year at this volume.') }}"
                        data-same-text="{{ __('Both plans pay the same at this volume.') }}"
                        data-empty-text="{{ __('Fill in all four figures to see your comparison.') }}"
                        class="mt-2 text-2xl font-semibold tracking-tight text-balance group-data-[tone=more]/results:text-teal-700 sm:text-3xl dark:group-data-[tone=more]/results:text-teal-300"
                    >
                        {{ __('Fill in all four figures to see your comparison.') }}
                    </p>

                    <dl class="mt-4 flex items-center justify-between gap-4 border-t border-neutral-200 pt-4 text-sm dark:border-neutral-800">
                        <dt class="text-neutral-600 dark:text-neutral-400">
                            {{ __('Difference over :years years', ['years' => $comparison['projection_years']]) }}
                        </dt>
                        <dd class="text-lg font-semibold tabular-nums" data-result="projectedDifference">—</dd>
                    </dl>
                </div>

                {{--
                    Both bars share one scale (0-100% of the same gross
                    commission), and each is labelled with its value as text,
                    so neither the colour nor the length has to be read alone.
                --}}
                <figure class="{{ $card }} p-5 sm:p-6">
                    <figcaption class="font-semibold">{{ __('Share of your commission you keep') }}</figcaption>
                    <div class="mt-4 space-y-4">
                        @foreach ([
                            'current' => ['label' => __('Your current brokerage'), 'bar' => 'bg-neutral-400 dark:bg-neutral-500'],
                            'company' => ['label' => $businessName, 'bar' => 'bg-teal-600 dark:bg-teal-400'],
                        ] as $side => $bar)
                            <div>
                                <div class="flex justify-between gap-4 text-sm">
                                    <span>{{ $bar['label'] }}</span>
                                    <span class="font-semibold tabular-nums" data-result="{{ $side }}.keptShare"
                                        >—</span>
                                </div>
                                <div
                                    class="mt-1.5 h-3 rounded-full bg-neutral-100 dark:bg-neutral-800"
                                    aria-hidden="true"
                                >
                                    <div
                                        data-bar="{{ $side }}"
                                        class="{{ $bar['bar'] }} h-full rounded-full transition-[width] duration-300"
                                        style="width: 0%"
                                    ></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </figure>

                <div class="{{ $card }} overflow-hidden">
                    <table class="w-full text-sm">
                        <caption class="px-5 pt-5 text-left font-semibold sm:px-6">
                            {{ __('Side by side, per year') }}
                        </caption>
                        <thead>
                            <tr class="border-b border-neutral-200 text-right text-xs text-neutral-500 dark:border-neutral-800 dark:text-neutral-400">
                                <th scope="col" class="px-5 py-3 text-left font-medium sm:px-6">
                                    <span class="sr-only">{{ __('Line item') }}</span>
                                </th>
                                <th scope="col" class="px-2 py-3 font-medium">{{ __('Current') }}</th>
                                <th
                                    scope="col"
                                    class="py-3 pr-5 pl-2 font-medium text-teal-700 sm:pr-6 dark:text-teal-300"
                                >
                                    {{ $businessName }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-200 dark:divide-neutral-800">
                            <tr>
                                <th scope="row" class="px-5 py-2.5 text-left font-normal sm:px-6">
                                    {{ __('Gross commission income') }}
                                </th>
                                <td class="px-2 py-2.5 text-right tabular-nums" data-result="grossCommission">—</td>
                                <td
                                    class="py-2.5 pr-5 pl-2 text-right tabular-nums sm:pr-6"
                                    data-result="grossCommission"
                                >
                                    —
                                </td>
                            </tr>
                            @foreach ($rows as $row)
                                <tr>
                                    <th scope="row" class="px-5 py-2.5 text-left font-normal sm:px-6">
                                        {{ $row['label'] }}
                                    </th>
                                    <td
                                        class="px-2 py-2.5 text-right tabular-nums"
                                        data-result="current.{{ $row['key'] }}"
                                    >
                                        —
                                    </td>
                                    <td
                                        class="py-2.5 pr-5 pl-2 text-right tabular-nums sm:pr-6"
                                        data-result="company.{{ $row['key'] }}"
                                    >
                                        —
                                    </td>
                                </tr>
                            @endforeach
                            <tr class="bg-neutral-50 text-base dark:bg-neutral-900">
                                <th scope="row" class="px-5 py-3 text-left font-semibold sm:px-6">
                                    {{ __('Net take-home') }}
                                </th>
                                <td
                                    class="px-2 py-3 text-right font-semibold tabular-nums"
                                    data-result="current.takeHome"
                                >
                                    —
                                </td>
                                <td
                                    class="py-3 pr-5 pl-2 text-right font-semibold text-teal-700 tabular-nums sm:pr-6 dark:text-teal-300"
                                    data-result="company.takeHome"
                                >
                                    —
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p class="text-xs leading-relaxed text-neutral-500 dark:text-neutral-400">
                    {{ __('Estimates for comparison only. Your current brokerage is worked out from the four figures you enter, with no caps or per-transaction fees; the royalty comes off the top and the split applies to the rest.') }}
                </p>
            </section>
        </div>
    </main>

    <x-calculators.assets />
</x-layouts::public>
