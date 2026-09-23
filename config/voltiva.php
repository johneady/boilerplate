<?php

/*
|--------------------------------------------------------------------------
| Voltiva Mobility
|--------------------------------------------------------------------------
|
| The few values the public site and the enquiry pipeline are built around.
| Content (cars, articles, page text) lives in the database and is edited in
| the admin panel; these are the structural choices that only change with a
| release.
|
*/

return [

    /*
     * The languages offered by the switcher in the header, keyed by locale
     * code. The label is each language's own name for itself, so a visitor
     * can find theirs whatever language the page is currently in. Adding a
     * language is a new entry here plus lang/<code>.json.
     */
    'locales' => [
        'en' => 'English',
        'es' => 'Español',
        'de' => 'Deutsch',
        'fr' => 'Français',
    ],

    /*
     * The language of the emails sent to the Voltiva team (new enquiry and
     * contact alerts). Explicit because the request's own locale -- and with
     * it config('app.locale'), which setLocale() rewrites -- is whatever the
     * customer picked in the language switcher.
     */
    'team_locale' => 'en',

    'currency' => 'EUR',

    /*
     * The automatic email sequence sent after an enquiry, as days after the
     * enquiry arrived. Day 0 goes out immediately with the confirmation.
     */
    'follow_up_days' => [0, 2, 5, 10, 20],

    /*
     * Finance estimates on the car pages: an illustrative representative APR
     * and the terms the calculator offers. Not an offer of credit.
     */
    'finance' => [
        'apr' => 6.9,
        'terms' => [24, 36, 48, 60],
        'default_term' => 48,
        'default_deposit_percent' => 20,
    ],

];
