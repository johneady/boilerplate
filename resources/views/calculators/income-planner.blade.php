{{--
    Calculator 1, for prospective agents: a desired take-home income worked
    back to the commission, closings, clients and weekly activity it needs.

    The page renders the form, the empty result slots and the assumptions;
    resources/js/calculators.js fills each [data-result] from the arithmetic in
    resources/js/calculators/formulas.js. Every number comes from
    config/calculators.php, so the copy here never states a rate itself.
--}}
@php
    $calculators = config('calculators');
    $planner = $calculators['income_planner'];
    $market = $calculators['market'];
    $plan = $calculators['company_plan'];
    $money = fn (int|float $amount): string => Number::currency($amount, $calculators['currency'], $calculators['locale'], 0);
    $percent = fn (int|float $value): string => (string) Number::percentage($value, 0, 2, $calculators['locale']);

    $card = 'rounded-2xl border border-neutral-200 bg-white/80 shadow-sm dark:border-neutral-800 dark:bg-neutral-900/60';

    $pipeline = [
        'closings' => __('Closed sales'),
        'listingsSold' => __('Listings sold'),
        'buyerClosings' => __('Buyer sales closed'),
        'listingsTaken' => __('Listings taken'),
        'buyerClients' => __('Buyer clients signed'),
        'listingAppointments' => __('Listing appointments'),
        'buyerConsultations' => __('Buyer consultations'),
        'conversations' => __('Conversations'),
    ];

    $assumptions = [
        __('Average sale price') => $money($market['average_sale_price']),
        __('Listing-side commission') => $percent($market['listing_commission_percent']),
        __('Buyer-side commission') => $percent($market['buyer_commission_percent']),
        __('Closings that are listings') => $percent($planner['listing_share_percent']),
        __('Listings that sell') => $percent($planner['listing_sold_percent']),
        __('Buyer clients who close') => $percent($planner['buyer_close_percent']),
        __('Listing appointments that sign') => $percent($planner['listing_appointment_percent']),
        __('Buyer consultations that sign') => $percent($planner['buyer_consultation_percent']),
        __('Conversations per appointment') => Number::format($planner['conversations_per_appointment'], locale: $calculators['locale']),
        __('Working weeks per year') => Number::format($planner['working_weeks'], locale: $calculators['locale']),
        __('Business expenses per year') => $money($planner['annual_business_expenses']),
    ];
@endphp

<x-layouts::public
    :title="__('Income planner')"
    :description="__('Enter the income you want as a real estate agent and see the listings, buyer clients and weekly appointments it takes. Free, no sign-up.')"
>
    <main class="flex-1 pt-2 pb-12 sm:pt-6">
        <x-calculators.switcher current="calculators.income-planner" class="sm:max-w-sm" />

        <div class="mt-6 max-w-2xl sm:mt-8">
            <p class="text-sm font-medium text-teal-700 dark:text-teal-300">{{ __('For prospective agents') }}</p>
            <h1 class="mt-1 text-3xl font-semibold tracking-tight text-balance sm:text-4xl">
                {{ __('What does your income goal take?') }}
            </h1>
            <p class="mt-3 leading-relaxed text-neutral-600 dark:text-neutral-400">
                {{ __('Enter the income you want to take home in a year. We work it back through the :business commission plan and typical business expenses to the listings, buyer clients and appointments that income needs.', ['business' => $businessName]) }}
            </p>
        </div>

        <div class="mt-8 grid gap-6 lg:grid-cols-[22rem_minmax(0,1fr)] lg:items-start">
            <form data-calculator="income-planner" class="{{ $card }} p-5 sm:p-6 lg:sticky lg:top-6" novalidate>
                <label for="income" class="block font-medium">{{ __('Your income goal') }}</label>
                <p id="income-help" class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                    {{ __('What you want to take home for the year, after splits and expenses.') }}
                </p>

                <div class="relative mt-3">
                    <span
                        class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-xl text-neutral-500 dark:text-neutral-400"
                        aria-hidden="true"
                    >
                        {{ $calculators['currency_symbol'] }}
                    </span>
                    <input
                        id="income"
                        name="income"
                        type="number"
                        inputmode="numeric"
                        min="0"
                        step="1000"
                        value="{{ $planner['default_income'] }}"
                        aria-describedby="income-help"
                        class="block w-full rounded-lg border border-neutral-300 bg-white py-3 pr-3 pl-8 text-2xl font-semibold tabular-nums shadow-xs focus:border-teal-600 focus:ring-2 focus:ring-teal-600/30 focus:outline-none dark:border-neutral-700 dark:bg-neutral-950 dark:focus:border-teal-400"
                    />
                </div>

                <input
                    name="income_range"
                    type="range"
                    data-mirror
                    min="{{ $planner['min_income'] }}"
                    max="{{ $planner['max_income'] }}"
                    step="{{ $planner['income_step'] }}"
                    value="{{ $planner['default_income'] }}"
                    aria-label="{{ __('Income goal slider') }}"
                    class="mt-5 w-full accent-teal-700 dark:accent-teal-400"
                />
                <div
                    class="mt-1 flex justify-between text-xs text-neutral-500 tabular-nums dark:text-neutral-400"
                    aria-hidden="true"
                >
                    <span>{{ $money($planner['min_income']) }}</span>
                    <span>{{ $money($planner['max_income']) }}</span>
                </div>

                <div role="group" aria-label="{{ __('Quick picks') }}" class="mt-5 grid grid-cols-4 gap-2">
                    @foreach ($planner['presets'] as $preset)
                        <button
                            type="button"
                            data-preset="{{ $preset }}"
                            class="rounded-lg border border-neutral-200 px-1 py-2 text-sm font-medium tabular-nums transition hover:border-teal-600 hover:text-teal-700 dark:border-neutral-700 dark:hover:border-teal-400 dark:hover:text-teal-300"
                        >
                            {{ Number::abbreviate($preset) }}
                        </button>
                    @endforeach
                </div>
            </form>

            <section
                data-results="income-planner"
                data-empty
                aria-labelledby="plan-heading"
                class="space-y-6 transition-opacity data-empty:opacity-60"
            >
                <h2 id="plan-heading" class="sr-only">{{ __('Your plan') }}</h2>

                <dl class="grid grid-cols-3 gap-2 sm:gap-4" aria-live="polite">
                    @foreach ([
                        'listingsTaken.year' => __('Listings to take a year'),
                        'buyerClients.year' => __('Buyer clients a year'),
                        'appointmentsPerWeek' => __('Appointments a week'),
                    ] as $key => $label)
                        <div class="{{ $card }} flex flex-col-reverse justify-end p-3 sm:p-5">
                            <dt class="mt-1 text-xs leading-snug text-neutral-600 sm:text-sm dark:text-neutral-400">
                                {{ $label }}
                            </dt>
                            <dd
                                class="text-3xl font-semibold tracking-tight text-teal-700 tabular-nums sm:text-4xl dark:text-teal-300"
                                data-result="{{ $key }}"
                            >
                                —
                            </dd>
                        </div>
                    @endforeach
                </dl>

                <dl class="flex items-center justify-between gap-4 rounded-xl bg-teal-50 px-4 py-3 text-sm text-teal-900 dark:bg-teal-400/10 dark:text-teal-100">
                    <dt>{{ __('Conversations with potential clients each working day') }}</dt>
                    <dd class="text-lg font-semibold tabular-nums" data-result="conversationsPerDay">—</dd>
                </dl>

                <div class="{{ $card }} p-5 sm:p-6">
                    <h3 class="font-semibold">{{ __('Where the money comes from') }}</h3>
                    <dl class="mt-4 divide-y divide-neutral-200 text-sm dark:divide-neutral-800">
                        <div class="flex justify-between gap-4 py-2.5">
                            <dt>{{ __('Your income goal') }}</dt>
                            <dd class="font-medium tabular-nums" data-result="desiredIncome">—</dd>
                        </div>
                        <div class="flex justify-between gap-4 py-2.5">
                            <dt>{{ __('Business expenses (MLS, licensing, marketing, car)') }}</dt>
                            <dd class="font-medium tabular-nums" data-result="businessExpenses">—</dd>
                        </div>
                        <div class="flex justify-between gap-4 py-2.5">
                            <dt>{{ __('Brokerage split to :business', ['business' => $businessName]) }}</dt>
                            <dd class="font-medium tabular-nums" data-result="companySplit">—</dd>
                        </div>
                        @if ((float) $plan['royalty_percent'] > 0)
                            <div class="flex justify-between gap-4 py-2.5">
                                <dt>{{ __('Royalties') }}</dt>
                                <dd class="font-medium tabular-nums" data-result="royalty">—</dd>
                            </div>
                        @endif
                        @if ((float) $plan['annual_fees'] > 0)
                            <div class="flex justify-between gap-4 py-2.5">
                                <dt>{{ __('Brokerage fees') }}</dt>
                                <dd class="font-medium tabular-nums" data-result="fees">—</dd>
                            </div>
                        @endif
                        <div class="flex justify-between gap-4 pt-3 text-base">
                            <dt class="font-semibold">{{ __('Commission income needed') }}</dt>
                            <dd
                                class="font-semibold text-teal-700 tabular-nums dark:text-teal-300"
                                data-result="grossCommission"
                            >
                                —
                            </dd>
                        </div>
                    </dl>
                </div>

                <div class="{{ $card }} overflow-hidden">
                    <table class="w-full text-sm">
                        <caption class="px-5 pt-5 text-left sm:px-6">
                            <span class="block font-semibold">{{ __('Your pipeline') }}</span>
                            <span class="mt-1 block text-neutral-600 dark:text-neutral-400">
                                {{ __('Rounded up, so each figure is the least you would need.') }}
                            </span>
                        </caption>
                        <thead>
                            <tr class="border-b border-neutral-200 text-right text-xs text-neutral-500 dark:border-neutral-800 dark:text-neutral-400">
                                <th scope="col" class="px-5 py-3 text-left font-medium sm:px-6">
                                    <span class="sr-only">{{ __('Activity') }}</span>
                                </th>
                                <th scope="col" class="px-2 py-3 font-medium">{{ __('Year') }}</th>
                                <th scope="col" class="px-2 py-3 font-medium">{{ __('Month') }}</th>
                                <th scope="col" class="py-3 pr-5 pl-2 font-medium sm:pr-6">{{ __('Week') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-200 dark:divide-neutral-800">
                            @foreach ($pipeline as $key => $label)
                                <tr>
                                    <th scope="row" class="px-5 py-2.5 text-left font-normal sm:px-6">{{ $label }}</th>
                                    <td
                                        class="px-2 py-2.5 text-right font-semibold tabular-nums"
                                        data-result="{{ $key }}.year"
                                    >
                                        —
                                    </td>
                                    <td class="px-2 py-2.5 text-right tabular-nums" data-result="{{ $key }}.month">
                                        —
                                    </td>
                                    <td
                                        class="py-2.5 pr-5 pl-2 text-right tabular-nums sm:pr-6"
                                        data-result="{{ $key }}.week"
                                    >
                                        —
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <details class="{{ $card }} group p-5 sm:p-6">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-semibold">
                        {{ __('How this is calculated') }}
                        <flux:icon.chevron-down class="size-4 transition group-open:rotate-180" />
                    </summary>

                    <x-calculators.plan-summary class="mt-4 text-sm text-neutral-600 dark:text-neutral-400" />

                    <dl class="mt-4 grid gap-x-8 text-sm sm:grid-cols-2">
                        @foreach ($assumptions as $label => $value)
                            <div class="flex justify-between gap-4 border-b border-neutral-200 py-2 dark:border-neutral-800">
                                <dt class="text-neutral-600 dark:text-neutral-400">{{ $label }}</dt>
                                <dd class="font-medium tabular-nums">{{ $value }}</dd>
                            </div>
                        @endforeach
                        <div class="flex justify-between gap-4 border-b border-neutral-200 py-2 dark:border-neutral-800">
                            <dt class="text-neutral-600 dark:text-neutral-400">
                                {{ __('Average commission per closing') }}
                            </dt>
                            <dd class="font-medium tabular-nums" data-result="averageCommission">—</dd>
                        </div>
                    </dl>
                </details>

                <p class="text-xs leading-relaxed text-neutral-500 dark:text-neutral-400">
                    {{ __('Estimates for planning only, based on the averages above. Your market, price point and conversion rates will differ.') }}
                </p>
            </section>
        </div>
    </main>

    <x-calculators.assets />
</x-layouts::public>
