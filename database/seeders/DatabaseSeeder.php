<?php

namespace Database\Seeders;

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
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,
        ]);

        if (! app()->environment('local', 'testing')) {
            return;
        }

        // Skipped when the admin address is also the test address, so this
        // non-admin factory user cannot land on top of the admin just seeded.
        if (config('first.user.email') === self::TEST_USER_EMAIL) {
            return;
        }

        // Guarded so a second `db:seed` against an already-seeded local
        // database doesn't fail on the users.email unique index.
        if (User::where('email', self::TEST_USER_EMAIL)->exists()) {
            return;
        }

        User::factory()->create([
            'name' => 'Test User',
            'email' => self::TEST_USER_EMAIL,
        ]);
    }
}
