<?php

return [

    /*
    |--------------------------------------------------------------------------
    | First User Configuration
    |--------------------------------------------------------------------------
    |
    | These credentials are used to create the first admin user when seeding
    | the database. This user has full access to the admin panel.
    |
    */

    'user' => [
        'name' => env('FIRST_USER_NAME', 'Admin User'),
        'email' => env('FIRST_USER_EMAIL', 'admin@example.com'),
        'password' => env('FIRST_USER_PASSWORD', 'password'),
    ],

];
