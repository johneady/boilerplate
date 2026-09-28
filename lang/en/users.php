<?php

/*
|--------------------------------------------------------------------------
| Admin Panel: Users
|--------------------------------------------------------------------------
|
| Strings for the Filament user resource. Dotted keys rather than the JSON
| file's English-as-key convention -- see .ai/rules/i18n.md for which applies
| where.
|
*/

return [

    'export' => 'Export users',

    'fields' => [
        'avatar' => 'Avatar',
        'email' => 'Email address',
        'email_verified' => 'Email verified',
        'registered' => 'Registered',
        'role' => 'Role',
        'verified' => 'Verified',
    ],

    'own_role_locked' => 'You cannot change your own role.',

    'status' => [
        'label' => 'Status',
        'active' => 'Active',
        'deactivated' => 'Deactivated',
    ],

    'deactivate' => [
        'label' => 'Deactivate',
        'heading' => 'Deactivate this account?',
        'description' => 'They will be signed out everywhere and unable to sign in. Their name stays on the posts, payments and refunds they worked on, and you can reactivate the account at any time.',
        'done' => 'Account deactivated',
        'has_running_subscription' => 'This account has a running subscription. Cancel it before deactivating the account.',
        'last_administrator' => 'This is the last active administrator, so the account was not deactivated.',
        'gateway_failed' => 'Their unfinished checkout could not be cancelled at the payment gateway, so the account was not deactivated. Please try again in a few minutes.',
    ],

    'reactivate' => [
        'label' => 'Reactivate',
        'heading' => 'Reactivate this account?',
        'description' => 'They will be able to sign in again with their existing password.',
        'done' => 'Account reactivated',
    ],

    'demote' => [
        'last_administrator' => 'This is the last active administrator, so their role was not changed.',
    ],

    'delete' => [
        'has_financial_records' => 'This account recorded payments or issued refunds, so it is kept for the financial records. Deactivate it instead.',
        'gateway_failed' => 'Their subscription could not be cancelled at the payment gateway, so the account was kept. Please try again in a few minutes.',
        'refused' => 'This account can no longer be deleted. Deactivate it instead.',
        'last_administrator' => 'This is the last active administrator, so the account was kept.',
    ],

];
