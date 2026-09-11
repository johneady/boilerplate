<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Blocked Environments
    |--------------------------------------------------------------------------
    |
    | Quick dev logins are offered in EVERY environment except these. The list
    | is a denylist rather than an allowlist on purpose: a bespoke environment
    | name ('staging', 'demo', 'review-42') should get the one-click logins
    | without having to be added here first.
    |
    | Where they are blocked the route is not registered at all, so a stray
    | request 404s rather than relying on a runtime guard.
    |
    | SECURITY: every environment not named here offers PASSWORDLESS login to
    | the seeded accounts, whose credentials are fixed and public (see
    | config/first.php). A deployed instance that is not APP_ENV=production is
    | therefore open to anyone who can reach its login page. Deploy real
    | instances as 'production'.
    |
    */

    'blocked_environments' => ['production'],

    /*
    |--------------------------------------------------------------------------
    | Accounts
    |--------------------------------------------------------------------------
    |
    | The accounts offered as one-click logins. The list is resolved on the
    | server and the browser only ever submits a position within it, so no
    | request can name an account the application did not itself offer.
    |
    | A null email is filled in from `first.user.email`, keeping the seeded
    | admin account described in one place. Resolving it here rather than
    | calling config() from this file avoids depending on config load order.
    |
    */

    'accounts' => [
        [
            'email' => null,
            'name' => null,
        ],
        [
            'email' => 'test@example.com',
            'name' => 'Test User',
        ],
    ],

];
