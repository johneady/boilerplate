<?php

/*
 * The server half of the two public calculators: the pages are open to
 * guests, and everything they show or hand to the browser follows
 * config/calculators.php. The arithmetic itself runs in JavaScript, so it is
 * covered by tests/Browser/CalculatorsTest.php.
 */

test('the :dataset opens for a guest with the calculator config embedded', function (string $routeName) {
    config()->set('calculators.market.average_sale_price', 612_345);

    $this->get(route($routeName))
        ->assertSuccessful()
        ->assertSee('<script type="application/json" id="calculator-config">', false)
        ->assertSee('"average_sale_price":612345', false);
})->with([
    'income planner' => 'calculators.income-planner',
    'split comparison' => 'calculators.split-comparison',
]);

test('the plan summary describes the default capped plan with no royalties', function () {
    $this->get(route('calculators.split-comparison'))
        ->assertSee('You keep 85% of every commission until')
        ->assertSee('has earned $14,000 from you in a year. After that, you keep 100%.')
        ->assertSee('No royalties or franchise fees.')
        ->assertDontSee('Annual brokerage fees');
});

test('the plan summary follows an uncapped split with a capped royalty and fees', function () {
    config()->set('calculators.company_plan', [
        'agent_split_percent' => 80,
        'company_dollar_cap' => null,
        'royalty_percent' => 5,
        'royalty_cap' => 3_000,
        'annual_fees' => 500,
    ]);

    $this->get(route('calculators.split-comparison'))
        ->assertSee('You keep 80% of every commission, all year.')
        ->assertSee('A 5% royalty, capped at $3,000 a year.')
        ->assertSee('Annual brokerage fees of $500.')
        ->assertDontSee('No royalties or franchise fees.');
});

test('the plan summary states an uncapped royalty', function () {
    config()->set('calculators.company_plan.royalty_percent', 6);

    $this->get(route('calculators.split-comparison'))
        ->assertSee('A 6% royalty on every commission.');
});

test('the income planner lists royalty and fee rows only when the plan charges them', function () {
    $this->get(route('calculators.income-planner'))
        ->assertDontSee('data-result="royalty"', false)
        ->assertDontSee('data-result="fees"', false);

    config()->set('calculators.company_plan.royalty_percent', 6);
    config()->set('calculators.company_plan.annual_fees', 500);

    $this->get(route('calculators.income-planner'))
        ->assertSee('data-result="royalty"', false)
        ->assertSee('data-result="fees"', false);
});

test('the income planner lists its assumptions from the config', function () {
    config()->set('calculators.market.average_sale_price', 612_345);
    config()->set('calculators.income_planner.listing_sold_percent', 90);

    $this->get(route('calculators.income-planner'))
        ->assertSee('$612,345')
        ->assertSeeInOrder(['Listings that sell', '90%']);
});
