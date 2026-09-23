<?php

/*
|--------------------------------------------------------------------------
| Agent calculators
|--------------------------------------------------------------------------
|
| Every variable behind the two public calculators lives in this one file. The
| pages hand it to the browser as JSON and resources/js/calculators does all of
| the arithmetic client-side, so changing a number here changes the results
| with no code edit (run `php artisan config:clear` if the config is cached).
|
| Percentages are written as percentages (2.75 means 2.75%), and money in
| whole dollars.
|
| The defaults below are placeholders researched from public 2025-2026 US
| figures, to be replaced with the brokerage's own parameters:
|
| - Average sale price: NAR existing-home median, August 2026 ($429,100).
| - Commission per side: 2026 survey averages, about 2.75% listing and 2.70%
|   buyer side after the 2024 NAR settlement.
| - Business expenses: NAR 2026 Member Profile median ($9,530 a year).
| - Conversion rates: common business-planning benchmarks (85-90% of properly
|   priced listings sell, 40-60% of appointments sign, one appointment per
|   10-15 real conversations).
|
*/

return [

    'currency' => 'USD',

    'locale' => 'en-US',

    // Shown in front of the money inputs.
    'currency_symbol' => '$',

    /*
    | The market both calculators price a transaction in.
    */
    'market' => [
        'average_sale_price' => 430_000,
        'listing_commission_percent' => 2.75,
        'buyer_commission_percent' => 2.70,
    ],

    /*
    | The brokerage's own compensation plan. The prospective-agent planner
    | works out the income it needs under this plan, and the split comparison
    | puts it beside the agent's current one.
    |
    | Royalty (franchise fee) comes off the gross first; the company split is
    | then taken from what remains. A null cap means uncapped.
    */
    'company_plan' => [
        'agent_split_percent' => 85,
        'company_dollar_cap' => 14_000,
        'royalty_percent' => 0,
        'royalty_cap' => null,
        'annual_fees' => 0,
    ],

    /*
    | Calculator 1: prospective agents. Works back from a desired take-home
    | income to the gross commission, closings, clients and weekly activity
    | that income needs.
    */
    'income_planner' => [
        'default_income' => 100_000,
        'min_income' => 20_000,
        'max_income' => 500_000,
        'income_step' => 5_000,
        'presets' => [60_000, 100_000, 150_000, 250_000],

        'annual_business_expenses' => 9_530,

        // Share of closings expected to be listings; the rest are buyers.
        // New agents are usually buyer-heavy.
        'listing_share_percent' => 40,

        // Listings taken that go on to sell.
        'listing_sold_percent' => 85,

        // Signed buyer clients who go on to close.
        'buyer_close_percent' => 75,

        // Listing appointments that turn into a signed listing.
        'listing_appointment_percent' => 50,

        // Buyer consultations that turn into a signed buyer client.
        'buyer_consultation_percent' => 50,

        // Real conversations it takes to set one appointment.
        'conversations_per_appointment' => 12,

        'working_weeks' => 48,
        'working_days_per_week' => 5,
    ],

    /*
    | Calculator 2: active agents. The values the form opens with.
    */
    'split_comparison' => [
        'default_sales_volume' => 6_000_000,
        'default_commission_percent' => 2.75,
        'default_split_percent' => 70,
        'default_royalty_percent' => 6,

        // The long-run difference is shown over this many years.
        'projection_years' => 5,
    ],

];
