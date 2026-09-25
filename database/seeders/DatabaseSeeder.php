<?php

namespace Database\Seeders;

use App\Auth\DevLoginAccounts;
use App\Auth\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * The password for the demo accounts declared in config/dev-login.php.
     *
     * Public and fixed, like the admin's: these accounts exist to be logged
     * into with one click on any non-production instance.
     */
    private const DEMO_ACCOUNT_PASSWORD = 'password';

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,
            SettingsSeeder::class,
            PagesSeeder::class,
        ]);

        // Seeded wherever the quick dev logins are offered -- which is every
        // environment but production -- so a deployed demo instance has the
        // accounts its login page advertises. Without this the buttons render
        // as "Not seeded" and there is no way to see the app as an ordinary
        // user or a staff role.
        if (! app(DevLoginAccounts::class)->enabled()) {
            return;
        }

        // Sample plans for the pricing page, behind the same gate: a demo
        // instance should show a working subscription flow the moment
        // payments are switched on, and production should start with none.
        $this->call(PlanSeeder::class);

        // The quick-login list is the single declaration of the demo
        // accounts: an entry with a role is one this seeder creates, so the
        // login page can never offer an account nothing seeds.
        foreach ((array) config('dev-login.accounts') as $account) {
            $role = Role::tryFrom((string) ($account['role'] ?? ''));
            $email = trim((string) ($account['email'] ?? ''));

            if ($role !== null && $email !== '') {
                $this->seedDemoAccount($email, trim((string) ($account['name'] ?? '')) ?: $email, $role);
            }
        }
    }

    /**
     * Create one demo account unless its address is already taken.
     */
    private function seedDemoAccount(string $email, string $name, Role $role): void
    {
        // Skipped when the admin address is also this address, so a
        // lesser-privileged account cannot land on top of the admin just
        // seeded -- and, guarded on existence generally, so a second
        // `db:seed` doesn't fail on the users.email unique index or reset an
        // account someone has since changed.
        if (config('first.user.email') === $email
            || User::where('email', $email)->exists()) {
            return;
        }

        // Built without the factory: factories call fake(), and fakerphp/faker
        // is a dev dependency absent from the --no-dev production image this
        // now runs in. See .ai/rules/seeders.md. The role is not
        // mass-assignable, so it is set as a property like the rest.
        $user = new User;
        $user->name = $name;
        $user->email = $email;
        $user->password = self::DEMO_ACCOUNT_PASSWORD;
        $user->role = $role;
        $user->email_verified_at = now();
        $user->save();
    }
}
