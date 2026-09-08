<?php

use App\Models\User;
use Database\Seeders\AdminUserSeeder;

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
