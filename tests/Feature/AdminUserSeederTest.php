<?php

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;

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

/**
 * The credentials are fixed demo values rather than environment-driven, so a
 * deployed instance seeds an admin with no configuration at all. The seeder
 * used to refuse its own defaults outside local/testing; that guard is gone
 * deliberately, and this asserts the replacement behaviour rather than leaving
 * the removal uncovered.
 */
test('it seeds the demo admin in every environment, including production', function (string $environment) {
    app()->detectEnvironment(fn () => $environment);

    (new AdminUserSeeder)->run();

    $admin = User::where('email', config('first.user.email'))->first();

    expect($admin)->not->toBeNull()
        ->and($admin->is_admin)->toBeTrue();
})->with(['local', 'staging', 'production']);

/**
 * The password is public, so changing it is the one manual step a real
 * deployment must take. A redeploy re-runs the seeder, and it must not undo it.
 */
test('re-seeding never resets a password changed after the first boot', function () {
    app()->detectEnvironment(fn () => 'production');

    (new AdminUserSeeder)->run();

    $admin = User::where('email', config('first.user.email'))->first();
    $admin->password = 'a-genuinely-strong-replacement';
    $admin->save();

    $changed = $admin->fresh()->password;

    (new AdminUserSeeder)->run();

    expect($admin->fresh()->password)->toBe($changed)
        ->and($admin->fresh()->is_admin)->toBeTrue();
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

/**
 * Item 1: a deployed demo instance must come up with the accounts its login
 * page offers. DatabaseSeeder used to gate the non-admin user to local/testing,
 * which left the quick-login button on a staging box rendering "Not seeded".
 */
test('the non-admin demo user is seeded wherever quick logins are offered', function (string $environment) {
    app()->detectEnvironment(fn () => $environment);

    $this->seed(DatabaseSeeder::class);

    expect(User::where('email', 'test@example.com')->exists())->toBeTrue();
})->with(['local', 'staging', 'demo']);

test('the non-admin demo user is not seeded in production', function () {
    app()->detectEnvironment(fn () => 'production');

    // Run the seeder directly: $this->seed() goes through the artisan command,
    // which prompts for confirmation when the environment is production.
    (new DatabaseSeeder)->run();

    expect(User::where('email', 'test@example.com')->exists())->toBeFalse()
        ->and(User::where('email', config('first.user.email'))->exists())->toBeTrue();
});

/**
 * The production image is installed with --no-dev, so fakerphp/faker is absent
 * and any factory call in a seeder that now runs there is a fatal error.
 * See .ai/rules/seeders.md.
 */
test('the demo user is built without the factory', function () {
    $source = file_get_contents(base_path('database/seeders/DatabaseSeeder.php'));

    expect($source)->not->toContain('factory()');
});

/**
 * The README and docker/README.md publish these credentials as the way into a
 * fresh instance, so an unusable password is a documented promise broken. The
 * 'hashed' cast is what makes assigning a plain string work; asserting the
 * password VERIFIES guards against that cast being removed.
 */
test('the seeded accounts can actually sign in with the documented password', function () {
    $this->seed(DatabaseSeeder::class);

    $admin = User::where('email', config('first.user.email'))->first();
    $demo = User::where('email', 'test@example.com')->first();

    expect(Hash::check(config('first.user.password'), $admin->password))->toBeTrue()
        ->and(Hash::check('password', $demo->password))->toBeTrue();
});
