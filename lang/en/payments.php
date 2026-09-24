<?php

/*
|--------------------------------------------------------------------------
| Admin Panel: Payments
|--------------------------------------------------------------------------
|
| Strings for the Filament payment, payment link, tax rate and webhook event
| screens and the payment settings. Dotted keys rather than the JSON file's
| English-as-key convention -- see .ai/rules/i18n.md. The customer-facing pay
| page, receipt and emails are in lang/en.json.
|
*/

return [

    'navigation_group' => 'Payments',

    'payment' => [
        'label' => 'Payment',
        'plural_label' => 'Payments',
    ],

    'link' => [
        'label' => 'Payment link',
        'plural_label' => 'Payment links',
    ],

    'tax_rate' => [
        'label' => 'Tax rate',
        'plural_label' => 'Tax rates',
    ],

    'webhook_event' => [
        'label' => 'Webhook event',
        'plural_label' => 'Webhook events',
    ],

    'fields' => [
        'reference' => 'Reference',
        'created' => 'Created',
        'customer' => 'Customer',
        'customer_name' => 'Customer name',
        'customer_email' => 'Customer email',
        'description' => 'For',
        'status' => 'Status',
        'gateway' => 'Gateway',
        'mode' => 'Mode',
        'subtotal' => 'Subtotal',
        'tax' => 'Tax',
        'total' => 'Total',
        'captured' => 'Captured',
        'refunded' => 'Refunded',
        'capture_method' => 'Capture',
        'capture_manual' => 'Held for capture',
        'capture_automatic' => 'Taken at checkout',
        'authorization_expires' => 'Hold expires',
        'paid_at' => 'Paid',
        'failure_reason' => 'Failure reason',
        'gateway_checkout_id' => 'Gateway checkout ID',
        'gateway_payment_id' => 'Gateway payment ID',
        'payable' => 'Paid for',
        'manual_method' => 'Received by',
        'manual_reference' => 'Receipt reference',
        'manual_received_on' => 'Received on',
        'recorded_by' => 'Recorded by',
        'amount' => 'Amount',
        'type' => 'Type',
        'source' => 'Source',
        'occurred' => 'When',
        'transaction_id' => 'Gateway transaction ID',
        'reason' => 'Reason',
        'initiated_by' => 'Refunded by',
        'initiated_by_gateway' => 'Gateway dashboard',
        'attempts' => 'Attempts',
        'event_id' => 'Event ID',
        'event_type' => 'Event type',
        'processed' => 'Processed',
        'error' => 'Error',
        'payload' => 'Payload',
    ],

    'links' => [
        'title' => 'Title',
        'title_help' => 'Shown to the customer and on their receipt.',
        'description' => 'Description',
        'description_help' => 'Optional details shown above the payment form.',
        'amount_type' => 'Amount',
        'amount' => 'Price (before tax)',
        'min_amount' => 'Minimum',
        'max_amount' => 'Maximum',
        'taxable' => 'Charge tax',
        'taxable_help' => 'Adds every active tax rate on top of the price.',
        'usage' => 'Usage',
        'expires_at' => 'Expires',
        'expires_at_help' => 'Optional. The link stops accepting payments after this.',
        'is_active' => 'Active',
        'is_active_help' => 'When off, the link answers 404.',
        'url' => 'Link',
        'payments_count' => 'Payments',
        'customer_entered' => 'Customer enters',
        'settled' => 'Paid',
        'copied' => 'Link copied',
        'open' => 'Open',
        'amount_positive' => 'Enter an amount greater than zero.',
        'max_below_min' => 'The maximum cannot be less than the minimum.',
    ],

    'tax_rates' => [
        'name' => 'Name',
        'name_help' => 'As it should appear on a receipt, e.g. HST, GST, PST or QST.',
        'percentage' => 'Rate (%)',
        'percentage_help' => 'Up to three decimal places, e.g. 13 or 9.975.',
        'is_active' => 'Active',
        'none_active' => 'No active tax rates: taxable items are charged no tax.',
    ],

    'actions' => [
        'refund' => 'Refund',
        'refund_heading' => 'Refund this payment',
        'refund_amount' => 'Amount to refund',
        'refund_amount_help' => 'Up to :amount can be refunded.',
        'refund_reason' => 'Reason (optional)',
        'refund_submit' => 'Refund',
        'refunded' => 'Refund issued',
        'refund_pending' => 'Refund requested',
        'refund_pending_body' => 'The gateway has not confirmed it yet. The payment updates when it does.',
        'refund_failed' => 'Refund failed',
        'capture' => 'Capture',
        'capture_heading' => 'Capture this held payment',
        'capture_amount' => 'Amount to capture',
        'capture_amount_help' => 'Up to :amount. Anything not captured is released to the customer.',
        'captured' => 'Payment captured',
        'capture_failed' => 'Capture failed',
        'void' => 'Void',
        'void_heading' => 'Release this hold?',
        'void_description' => 'The customer\'s funds are released and nothing is charged. This cannot be undone.',
        'voided' => 'Hold released',
        'void_failed' => 'Void failed',
        'record_manual' => 'Record payment',
        'record_manual_heading' => 'Record a payment received outside the site',
        'record_manual_amount' => 'Amount received (before tax)',
        'record_manual_amount_help' => 'Tax is added as at checkout when this item is taxable.',
        'recorded' => 'Payment recorded',
        'record_failed' => 'Payment not recorded',
        'retry' => 'Retry',
        'retried' => 'Event queued for processing',
        'gateway_error' => 'The gateway said: :message',
    ],

    'settings' => [
        'stripe_button' => 'Stripe credentials',
        'paypal_button' => 'PayPal credentials',
        'credentials_heading' => ':gateway credentials',
        'credentials_description' => 'Secrets are stored encrypted and never shown again. Leave a secret blank to keep the one already saved.',
        'secret_set' => 'Saved: :masked. Leave blank to keep it.',
        'secret_unset' => 'Not set.',
        'current_password' => 'Your password',
        'current_password_help' => 'Changing payment credentials requires your password.',
        'credentials_saved' => 'Credentials saved',
        'credentials_unchanged' => 'Nothing was changed',
        'webhook_urls' => 'Webhook URLs',
        'webhook_urls_help' => 'Point each gateway\'s webhook endpoint at the URL for its mode.',
    ],

];
