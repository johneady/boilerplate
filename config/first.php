<?php

return [

    /*
    |--------------------------------------------------------------------------
    | First User Configuration
    |--------------------------------------------------------------------------
    |
    | The admin account AdminUserSeeder creates. This user has full access to
    | the admin panel.
    |
    | These are FIXED DEMO CREDENTIALS, not environment-driven: this is a
    | boilerplate, and every deployment of it is a demo instance that should
    | come up usable with no configuration at all. Nothing here reads env(),
    | so there is no FIRST_USER_* to set and nothing to forget.
    |
    | The consequence is deliberate and must be understood before deploying:
    | every instance seeded from this file has a PUBLICLY KNOWN admin login.
    | Before putting an instance in front of anyone who should not have admin
    | rights, change this password from the account's own settings page --
    | re-seeding never resets it (see AdminUserSeeder).
    |
    */

    'user' => [
        'name' => 'Admin User',
        'email' => 'admin@example.com',
        'password' => 'password',
    ],

];
