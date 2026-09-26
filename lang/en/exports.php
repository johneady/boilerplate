<?php

return [

    'completed' => '{1} Your export is ready: :count row.|[2,*] Your export is ready: :count rows.',
    'failed' => '{1} :count row could not be exported.|[2,*] :count rows could not be exported.',
    'yes' => 'Yes',
    'no' => 'No',
    'export_refunds' => 'Export refunds',

    'payments' => [
        'receipt_number' => 'Receipt number',
        'paid_on' => 'Paid on',
        'customer_name' => 'Customer',
        'customer_email' => 'Email',
        'description' => 'Description',
        'status' => 'Status',
        'method' => 'Method',
        'method_reference' => 'Method reference',
        'mode' => 'Mode',
        'currency' => 'Currency',
        'subtotal' => 'Subtotal',
        'tax_total' => 'Tax total',
        'total' => 'Total',
        'paid' => 'Paid',
        'refunded' => 'Refunded',
        'net' => 'Net',
        'reference' => 'Reference',
        'gateway_reference' => 'Gateway reference',
        'created_at' => 'Created',
    ],

    'refunds' => [
        'refunded_on' => 'Refunded on',
        'receipt_number' => 'Receipt number',
        'customer_name' => 'Customer',
        'description' => 'Description',
        'currency' => 'Currency',
        'amount' => 'Amount',
        'tax_amount' => 'Tax refunded',
        'status' => 'Status',
        'reason' => 'Reason',
        'payment_reference' => 'Payment reference',
        'from' => 'Refunded from',
        'until' => 'Refunded until',
    ],

    'users' => [
        'name' => 'Name',
        'email' => 'Email',
        'role' => 'Role',
        'verified' => 'Email verified',
        'registered' => 'Registered',
        'subscription' => 'Subscription',
        'subscription_state' => ':plan (:status)',
    ],

];
