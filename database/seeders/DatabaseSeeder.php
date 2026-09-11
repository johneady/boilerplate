<?php

namespace Database\Seeders;

use App\Auth\DevLoginAccounts;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * The address of the non-admin convenience account seeded locally.
     */
    private const TEST_USER_EMAIL = 'test@example.com';

    /**
     * The password for that account.
     *
     * Public and fixed, like the admin's: this account exists to be logged
     * into with one click on any non-production instance.
     */
    private const TEST_USER_PASSWORD = 'password';

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,
            SettingsSeeder::class,
        ]);

        // Seeded wherever the quick dev logins are offered -- which is every
        // environment but production -- so a deployed demo instance has the
        // non-admin account its login page advertises. Without this the button
        // renders as "Not seeded" and there is no way to see the app as an
        // ordinary user.
        if (! app(DevLoginAccounts::class)->enabled()) {
            return;
        }

        // Skipped when the admin address is also the test address, so this
        // non-admin user cannot land on top of the admin just seeded.
        if (config('first.user.email') === self::TEST_USER_EMAIL) {
            return;
        }

        // Guarded so a second `db:seed` against an already-seeded database
        // doesn't fail on the users.email unique index.
        if (User::where('email', self::TEST_USER_EMAIL)->exists()) {
            return;
        }

        // Built without the factory: factories call fake(), and fakerphp/faker
        // is a dev dependency absent from the --no-dev production image this
        // now runs in. See .ai/rules/seeders.md.
        $user = new User;
        $user->name = 'Test User';
        $user->email = self::TEST_USER_EMAIL;
        $user->password = self::TEST_USER_PASSWORD;
        $user->email_verified_at = now();
        $user->save();
    }
}
