{{--
    The company's compensation plan in a sentence or two, built from
    config/calculators.php so the copy can never disagree with the numbers the
    calculators use. Each clause is its own translatable sentence with
    placeholders rather than fragments joined together.
--}}
@php
    $calculators = config('calculators');
    $plan = $calculators['company_plan'];
    $money = fn (int|float $amount): string => Number::currency($amount, $calculators['currency'], $calculators['locale'], 0);
    $percent = fn (int|float $value): string => (string) Number::percentage($value, 0, 2, $calculators['locale']);
@endphp

<p {{ $attributes->class('leading-relaxed') }}>
    @if ($plan['company_dollar_cap'] !== null)
        {{ __('You keep :split of every commission until :business has earned :cap from you in a year. After that, you keep 100%.', ['split' => $percent($plan['agent_split_percent']), 'business' => $businessName, 'cap' => $money($plan['company_dollar_cap'])]) }}
    @else
        {{ __('You keep :split of every commission, all year.', ['split' => $percent($plan['agent_split_percent'])]) }}
    @endif

    @if ((float) $plan['royalty_percent'] === 0.0)
        {{ __('No royalties or franchise fees.') }}
    @elseif ($plan['royalty_cap'] !== null)
        {{ __('A :royalty royalty, capped at :cap a year.', ['royalty' => $percent($plan['royalty_percent']), 'cap' => $money($plan['royalty_cap'])]) }}
    @else
        {{ __('A :royalty royalty on every commission.', ['royalty' => $percent($plan['royalty_percent'])]) }}
    @endif

    @if ((float) $plan['annual_fees'] > 0)
        {{ __('Annual brokerage fees of :fees.', ['fees' => $money($plan['annual_fees'])]) }}
    @endif
</p>
