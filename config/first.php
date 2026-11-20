<?php

/*
|--------------------------------------------------------------------------
| First User Configuration
|--------------------------------------------------------------------------
|
| The admin account AdminUserSeeder creates. This user has full access to
| the admin panel.
|
| The web installer (installer-toolkit's install.php) writes the admin its
| buyer chose as FIRST_USER_EMAIL / FIRST_USER_FIRST_NAME /
| FIRST_USER_LAST_NAME / FIRST_USER_PASSWORD, seeds, then BLANKS those
| values in .env (the keys stay, empty) so the password does not sit on disk.
|
| Two different absences, deliberately treated differently:
|
|   Key missing (Docker, a fresh clone): the fixed demo admin below, so a
|   deployed instance comes up usable with nothing to configure. Its login
|   is PUBLICLY KNOWN -- change the password from the account's settings
|   page; re-seeding never resets it.
|
|   Key present but blank (an installed copy, after the installer scrubbed
|   it): an empty email, and AdminUserSeeder seeds nothing. Falling back to
|   the demo values there would add a second admin with a public password
|   to a live install the next time anything ran the seeder.
|
| env()'s default applies only when the key is missing, which is exactly
| that split.
|
*/

$firstName = trim((string) env('FIRST_USER_FIRST_NAME', ''));
$lastName = trim((string) env('FIRST_USER_LAST_NAME', ''));
$installerName = trim($firstName.' '.$lastName);

return [

    'user' => [
        'name' => $installerName !== '' ? $installerName : env('FIRST_USER_NAME', 'Admin User'),
        'email' => trim((string) env('FIRST_USER_EMAIL', 'admin@example.com')),
        'password' => (string) env('FIRST_USER_PASSWORD', 'password'),
    ],

];
