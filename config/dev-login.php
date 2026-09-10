<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Allowed Environments
    |--------------------------------------------------------------------------
    |
    | Quick dev logins are only offered, and only honoured, in these
    | environments. Anywhere else the route is not registered at all, so a
    | stray request 404s rather than relying on a runtime guard.
    |
    */

    'allowed_environments' => ['local', 'testing'],

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
