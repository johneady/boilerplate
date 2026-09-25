<?php

use Stripe\Util\ApiVersion;

return [

    /*
    |--------------------------------------------------------------------------
    | Payments
    |--------------------------------------------------------------------------
    |
    | Operational tuning for the payments module in app/Payments. What an
    | administrator decides -- whether payments are on, the mode, the currency,
    | which gateways are offered and their credentials -- lives in the settings
    | table (App\Settings\SettingKey) and is edited from Admin -> Settings ->
    | Payments. This file holds only what the deployer tunes: timings, API
    | endpoints and the Stripe API version the code was written against.
    |
    */

    /*
     * How long a hosted checkout page stays usable, in minutes.
     *
     * Passed to Stripe as the Checkout Session's expires_at, which Stripe
     * bounds between 30 minutes and 24 hours. PayPal orders carry no expiry of
     * their own; a PayPal checkout is abandoned by the same local timer below.
     */
    'checkout_expiry_minutes' => 60,

    /*
     * Printed before every receipt number, which is zero-padded to six digits:
     * "R-000123". The numbers themselves come from App\Payments\ReceiptNumbers.
     */
    'receipt_prefix' => 'R-',

    /*
     * How long a checkout may sit unpaid before it is marked expired, in hours.
     *
     * Deliberately longer than checkout_expiry_minutes: payments:expire-checkouts
     * re-reads the gateway first, so a customer who completed payment in the
     * last minute of the hosted page's life is recorded as paid, not expired.
     */
    'abandoned_after_hours' => 24,

    /*
     * How long a payment, refund or capture may sit in an in-flight state
     * before payments:reconcile-stale re-reads it from the gateway, in minutes.
     *
     * This is the recovery path for a process that died between calling the
     * gateway and recording the result. No database transaction is held across
     * a gateway call (it could not roll back a charge the gateway already
     * made), so re-reading the gateway is what settles such a record.
     */
    'stale_after_minutes' => 15,

    /*
     * The most in-flight records one reconcile-stale run will touch.
     *
     * Each is at least one gateway API call; the cap keeps a backlog from
     * turning one scheduled run into a burst that trips the gateway's own
     * rate limits. The remainder is picked up by the next run.
     */
    'reconcile_batch_size' => 100,

    /*
     * How far ahead of an authorization's expiry operators are warned, in hours.
     */
    'authorization_warning_hours' => 24,

    /*
     * How long received webhook events are kept, in days.
     *
     * The payload of a webhook carries the customer's name and email, so the
     * table is trimmed on a schedule rather than kept forever. The ledger and
     * the payment rows are the durable record; an event is only the message
     * that told us about a change.
     */
    'webhook_retention_days' => 90,

    /*
     * Outbound HTTP timeouts for gateway API calls, in seconds.
     *
     * Short enough that a gateway outage cannot occupy a PHP-FPM worker or a
     * queue worker for long, and well inside App\Jobs\Job::$timeout (60s) so
     * a job that retries once still finishes before the worker kills it.
     */
    'http' => [
        'connect_timeout' => 5,
        'timeout' => 20,
    ],

    'stripe' => [
        /*
         * The Stripe API version every request is pinned to.
         *
         * Taken from the installed SDK so request and response shapes match
         * the objects stripe-php deserialises. Upgrading the SDK moves this
         * with it -- re-run the Sandbox test suite when it does.
         */
        'api_version' => ApiVersion::CURRENT,
    ],

    'paypal' => [
        'base_urls' => [
            'sandbox' => 'https://api-m.sandbox.paypal.com',
            'live' => 'https://api-m.paypal.com',
        ],

        /*
         * Seconds shaved off the OAuth token's own lifetime when caching it,
         * so a token is never presented in the last moments before it lapses.
         */
        'token_expiry_margin' => 60,
    ],

];
