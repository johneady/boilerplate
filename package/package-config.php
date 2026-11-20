<?php

use Database\Seeders\AdminUserSeeder;
use Database\Seeders\PagesSeeder;
use Database\Seeders\SettingsSeeder;

/**
 * What `php artisan package:build` needs to know about this application.
 *
 * The builder leaves this file out of the zip; the installed app reads its
 * slug from config/updates.php (UPDATE_SLUG), which must match.
 */
return [
    'name' => 'Boilerplate',
    'slug' => 'boilerplate',

    // Built .update packages are signed with this key, and config/updates.php
    // trusts it. package:build fails unless it finds the private key:
    // UPDATE_SIGNING_KEY (the CI secret), the file UPDATE_SIGNING_KEY_FILE
    // names, or ($XDG_CONFIG_HOME or ~/.config)/installer-toolkit/signing.key.
    'signing_key_id' => 'key-2026-07-b',

    // Run on every install: the administrator the installer's admin step
    // describes (FIRST_USER_*, see config/first.php), the default settings and
    // the legal pages.
    'essential_seeders' => [
        AdminUserSeeder::class,
        SettingsSeeder::class,
        PagesSeeder::class,
    ],

    // The installer's "full demonstration data" choice. Deliberately empty:
    // the demo seeders (PlanSeeder, BlogSeeder, DemoBusinessSeeder) assume the
    // quick-login demo accounts, whose passwords are public, and a buyer's
    // install must not gain those. DatabaseSeeder still runs them wherever
    // dev logins are offered.
    'sample_seeders' => [],

    // Ship docs/ and CHANGELOG.md: buyers read them under Admin → System →
    // Documentation (App\Filament\Pages\Documentation), and readme.html says so.
    'include_docs' => true,

    'demo' => [
        'enabled' => false,
    ],
];
