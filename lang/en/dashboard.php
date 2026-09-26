<?php

return [

    'overview' => [
        'heading' => 'This month so far',
        'revenue' => 'Revenue this month',
        'new_customers' => 'New customers this month',
        'subscriptions' => 'Active subscriptions',
        'mrr' => ':amount a month recurring',
        'refunded' => 'Refunded this month',
        'taken' => 'Of :amount taken this month',
        'up' => ':percent% up on last month so far',
        'down' => ':percent% down on last month so far',
        'no_comparison' => 'Nothing to compare with last month yet',
        'level' => 'Level with last month so far',
    ],

    'revenue_chart' => [
        'heading' => 'Revenue by month',
        'description' => 'Net of refunds, in :currency.',
        'dataset' => 'Net revenue',
    ],

    'attention' => [
        'heading' => 'Needs attention',
        'all_clear' => 'Nothing needs your attention right now.',
        'disputes' => '{1} :count dispute needs a response|[2,*] :count disputes need a response',
        'past_due' => '{1} :count subscription renewal failed|[2,*] :count subscription renewals failed',
        'holds' => '{1} :count held payment expires soon|[2,*] :count held payments expire soon',
        'messages' => '{1} :count contact message is unanswered|[2,*] :count contact messages are unanswered',
    ],

];
