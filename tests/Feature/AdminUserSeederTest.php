<?php

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\DatabaseSeeder;

test('it seeds an admin user from configuration', function () {
    config(['first.user.name' => 'Seeded Admin', 'first.user.email' => 'seeded@example.com']);

    $this->seed(AdminUserSeeder::class);

    $user = User::where('email', 'seeded@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->is_admin)->toBeTrue()
        ->and($user->name)->toBe('Seeded Admin');
});

test('it does not overwrite an existing admin', function () {
    config(['first.user.email' => 'seeded@example.com']);

    $this->seed(AdminUserSeeder::class);
    $original = User::where('email', 'seeded@example.com')->first();

    $this->seed(AdminUserSeeder::class);

    expect(User::where('email', 'seeded@example.com')->count())->toBe(1)
        ->and(User::where('email', 'seeded@example.com')->first()->password)->toBe($original->password);
});

test('it promotes an existing non-admin on the configured address', function () {
    config(['first.user.email' => 'seeded@example.com']);

    $user = User::factory()->create([
        'email' => 'seeded@example.com',
        'name' => 'Abigail Mills V',
    ]);

    expect($user->is_admin)->toBeFalse();

    $this->seed(AdminUserSeeder::class);

    expect($user->fresh()->is_admin)->toBeTrue();
});

test('promoting an existing user leaves their name and password alone', function () {
    config(['first.user.name' => 'Seeded Admin', 'first.user.email' => 'seeded@example.com']);

    $user = User::factory()->create([
        'email' => 'seeded@example.com',
        'name' => 'Abigail Mills V',
    ]);

    $this->seed(AdminUserSeeder::class);

    expect($user->fresh()->name)->toBe('Abigail Mills V')
        ->and($user->fresh()->password)->toBe($user->password)
        ->and(User::where('email', 'seeded@example.com')->count())->toBe(1);
});

test('it refuses insecure default credentials outside local and testing', function () {
    app()->detectEnvironment(fn () => 'production');

    config(['first.user.email' => 'admin@example.com', 'first.user.password' => 'password']);

    expect(fn () => (new AdminUserSeeder)->run())
        ->toThrow(RuntimeException::class, 'Refusing to seed the admin user');

    expect(User::where('email', 'admin@example.com')->exists())->toBeFalse();
});

test('it seeds in production when credentials are configured', function () {
    app()->detectEnvironment(fn () => 'production');

    config([
        'first.user.email' => 'real-admin@example.com',
        'first.user.password' => 'a-genuinely-strong-password',
    ]);

    (new AdminUserSeeder)->run();

    expect(User::where('email', 'real-admin@example.com')->first()->is_admin)->toBeTrue();
});

test('the full seed leaves the configured admin an admin', function () {
    config(['first.user.email' => 'seeded@example.com']);

    $this->seed(DatabaseSeeder::class);

    expect(User::where('email', 'seeded@example.com')->first()->is_admin)->toBeTrue()
        ->and(User::where('email', 'test@example.com')->first()->is_admin)->toBeFalse();
});

test('the test user does not demote an admin that shares its address', function () {
    config(['first.user.email' => 'test@example.com', 'first.user.password' => 'password']);

    $this->seed(DatabaseSeeder::class);

    expect(User::where('email', 'test@example.com')->count())->toBe(1)
        ->and(User::where('email', 'test@example.com')->first()->is_admin)->toBeTrue();
});

test('seeding twice is idempotent', function () {
    config(['first.user.email' => 'seeded@example.com']);

    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    expect(User::where('email', 'seeded@example.com')->count())->toBe(1)
        ->and(User::where('email', 'test@example.com')->count())->toBe(1);
});
