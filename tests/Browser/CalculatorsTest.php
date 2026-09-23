<?php

/*
 * The calculators' arithmetic runs in the browser (resources/js/calculators),
 * so this is the only place it can be checked. The expected figures are
 * worked by hand from the default config/calculators.php:
 *
 * Income planner, $100,000 goal: + $9,530 expenses = $109,530 to keep. At an
 * 85/15 split the company's $14,000 cap is reached at $93,333, so the gross
 * needed is $109,530 + $14,000 = $123,530. A closing averages
 * 40% x $11,825 + 60% x $11,610 = $11,696, so 10.56 closings: 4.22 listings
 * sold / 85% = 4.97 taken (5), 6.34 buyer closings / 75% = 8.45 clients (9),
 * and (9.94 + 16.90) appointments / 48 weeks = 0.56 a week (0.6).
 *
 * Split comparison, defaults ($6M at 2.75%, 70% split, 6% royalty): gross
 * $165,000. Current: $9,900 royalty, 30% of $155,100 = $46,530 split, so
 * $108,570. Company: 15% capped at $14,000, so $151,000. Difference $42,430.
 */

test('the income planner works a goal back to listings, buyers and appointments', function () {
    $page = visit('/income-planner');

    $page->fill('income', '100000')
        ->assertSeeIn('[data-results] dd[data-result="grossCommission"]', '$123,530')
        ->assertSeeIn('[data-results] dd[data-result="listingsTaken.year"]', '5')
        ->assertSeeIn('[data-results] dd[data-result="buyerClients.year"]', '9')
        ->assertSeeIn('[data-results] dd[data-result="appointmentsPerWeek"]', '0.6')
        ->assertNoJavaScriptErrors();
});

test('the income planner reruns from a preset and shows dashes for an empty goal', function () {
    $page = visit('/income-planner');

    // $250,000 + $9,530 = $259,530 kept, plus the $14,000 cap.
    $page->click('button[data-preset="250000"]')
        ->assertSeeIn('dd[data-result="grossCommission"]', '$273,530')
        ->fill('income', '')
        ->assertSeeIn('dd[data-result="grossCommission"]', '—')
        ->assertNoJavaScriptErrors();
});

test('the income planner restores a goal shared in the link', function () {
    // $150,000 + $9,530 + $14,000 cap.
    visit('/income-planner?income=150000')
        ->assertValue('income', '150000')
        ->assertSeeIn('dd[data-result="grossCommission"]', '$173,530')
        ->assertNoJavaScriptErrors();
});

test('the split comparison shows the default production under both plans', function () {
    visit('/split-comparison')
        ->assertSeeIn('td[data-result="current.takeHome"]', '$108,570')
        ->assertSeeIn('td[data-result="company.takeHome"]', '$151,000')
        ->assertSeeIn('[data-headline]', 'You could take home $42,430 more a year')
        // Five years of the difference.
        ->assertSeeIn('[data-result="projectedDifference"]', '$212,150')
        ->assertNoJavaScriptErrors();
});

test('the split comparison says so when the current plan pays more', function () {
    // $1M at 2.5% = $25,000 gross. Current 95% with no royalty keeps $23,750;
    // the company's 15% ($3,750, under the cap) leaves $21,250.
    visit('/split-comparison')
        ->fill('sales_volume', '1000000')
        ->fill('commission_percent', '2.5')
        ->fill('split_percent', '95')
        ->fill('royalty_percent', '0')
        ->assertSeeIn('[data-headline]', 'Your current plan pays $2,500 more a year at this volume.')
        ->assertNoJavaScriptErrors();
});

test('the split comparison waits for every figure before comparing', function () {
    visit('/split-comparison')
        ->fill('sales_volume', '')
        ->assertSeeIn('[data-headline]', 'Fill in all four figures to see your comparison.')
        ->assertSeeIn('td[data-result="company.takeHome"]', '—')
        ->fill('sales_volume', '6000000')
        ->fill('split_percent', '120')
        ->assertSeeIn('[data-headline]', 'Fill in all four figures to see your comparison.')
        ->assertNoJavaScriptErrors();
});
