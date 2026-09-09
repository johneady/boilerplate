<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    /**
     * Seed the admin user from environment configuration.
     */
    public function run(): void
    {
        $name = config('first.user.name');
        $email = config('first.user.email');
        $password = config('first.user.password');

        $this->guardAgainstInsecureDefaults($email, $password);

        // Idempotent by design: seeding may run on every boot of a deployed
        // instance, and creating the user again would fail on the users.email
        // unique index. An existing account keeps its name and password, so an
        // operator who has since changed them does not have them silently reset
        // on the next deploy -- but admin rights are still asserted, since the
        // whole point of this seeder is that this address can reach the panel.
        // Without that, a first user created by the factory (which defaults
        // is_admin to false) stays locked out of the panel forever.
        $existingUser = User::where('email', $email)->first();

        if ($existingUser) {
            $this->promoteToAdmin($existingUser);

            return;
        }

        // Built without the factory on purpose: factories depend on
        // fakerphp/faker, a dev dependency absent from a --no-dev production
        // install, and this seeder must run there. is_admin is not
        // mass-assignable, so it is set explicitly rather than passed to fill().
        $user = new User;
        $user->name = $name;
        $user->email = $email;
        $user->password = $password;
        $user->email_verified_at = now();
        $user->is_admin = true;
        $user->save();
    }

    /**
     * Grant admin rights to an account that already exists.
     *
     * Only the flag is touched: an operator's own name and password changes on
     * the seeded address are left alone.
     */
    private function promoteToAdmin(User $user): void
    {
        if ($user->is_admin) {
            return;
        }

        $user->is_admin = true;
        $user->save();
    }

    /**
     * Refuse to seed a trivially guessable admin account outside local/testing.
     *
     * The defaults exist so a fresh clone works with no configuration. Anywhere
     * else they would leave a known-credential admin on a real deployment.
     */
    private function guardAgainstInsecureDefaults(?string $email, ?string $password): void
    {
        if (app()->environment(['local', 'testing'])) {
            return;
        }

        $insecureEmail = in_array($email, [null, '', 'admin@example.com'], true);
        $insecurePassword = in_array($password, [null, '', 'password'], true);

        if ($insecureEmail || $insecurePassword) {
            throw new RuntimeException(
                'Refusing to seed the admin user with insecure default credentials in the '
                .app()->environment().' environment. Set FIRST_USER_EMAIL and a strong '
                .'FIRST_USER_PASSWORD in your .env before seeding.'
            );
        }
    }
}
