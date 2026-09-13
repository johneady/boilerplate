<?php

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;

test('the versioned ping route is registered under its prefix', function () {
    $this->getJson('/api/v1/ping')
        ->assertOk()
        ->assertExactJson(['data' => ['status' => 'ok', 'version' => 'v1']]);
});

test('api routes are registered under a version-prefixed route name', function () {
    expect(route('api.v1.ping', absolute: false))->toBe('/api/v1/ping');
});

/**
 * The api middleware group carries no session, so a route here must not
 * silently gain cookie authentication from the web group. A route that did
 * would appear to work in the browser and fail for every real API client.
 */
test('api routes are stateless', function () {
    $middleware = collect(Route::getRoutes()->getByName('api.v1.ping')->gatherMiddleware());

    expect($middleware)->not->toContain(StartSession::class)
        ->and($middleware)->not->toContain(EncryptCookies::class);
});

/**
 * bootstrap/app.php renders JSON for anything under api/*. These cover the
 * error shapes rather than the happy path, because that is what regresses
 * unnoticed -- an HTML error page reaching an API client is a parse failure at
 * the other end, not a readable error.
 */
test('an unknown api route returns a json 404 rather than an html error page', function () {
    $response = $this->get('/api/v1/does-not-exist', ['Accept' => 'text/html']);

    $response->assertNotFound()
        ->assertHeader('content-type', 'application/json');

    expect($response->json())->toHaveKey('message');
});

test('a validation failure under the api prefix returns json 422', function () {
    Route::middleware('api')->post('/api/v1/testing-validation', function () {
        request()->validate(['name' => ['required']]);
    });

    $this->post('/api/v1/testing-validation', [], ['Accept' => 'text/html'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('name');
});

test('the user resource publishes the public fields', function () {
    $user = User::factory()->create(['name' => 'Ada Lovelace', 'email' => 'ada@example.com']);

    $payload = UserResource::make($user)->toArray(request());

    expect($payload)->toHaveKeys(['id', 'name', 'email', 'role', 'created_at'])
        ->and($payload['name'])->toBe('Ada Lovelace')
        ->and($payload['email'])->toBe('ada@example.com');
});

/**
 * Resolving an avatar URL stats the images disk, which in a collection is one
 * round-trip per user once IMAGE_DISK is remote. It is therefore opt-in, and
 * these pin both halves of that: absent unless asked for, present when asked.
 *
 * Asserted through the rendered response rather than toArray(), because
 * when() yields a placeholder that only the response pipeline strips out.
 */
test('the user resource omits the avatar url by default', function () {
    $user = User::factory()->create();

    $payload = UserResource::make($user)->response()->getData(true)['data'];

    expect($payload)->not->toHaveKey('avatar_url');
});

test('the user resource publishes the avatar url when asked', function () {
    $user = User::factory()->create();

    $payload = UserResource::make($user)->withAvatarUrl()->response()->getData(true)['data'];

    // Null rather than the gradient initials data URI: the panel's fallback
    // avatar is a Filament concern, and baking it in here would ship a
    // base64 blob per user to API consumers that render their own initials.
    expect($payload)->toHaveKey('avatar_url')
        ->and($payload['avatar_url'])->toBeNull();
});

/**
 * The resource lists its fields explicitly so that a column added to the users
 * table is never published by default. These are the ones that must never
 * appear whatever else changes.
 */
test('the user resource never publishes credentials', function () {
    $user = User::factory()->create();

    $payload = UserResource::make($user)->toArray(request());

    expect($payload)->not->toHaveKeys([
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ]);
});

test('resources are wrapped in a data envelope', function () {
    $user = User::factory()->create();

    $wrapped = UserResource::make($user)->response()->getData(true);

    expect($wrapped)->toHaveKey('data')
        ->and($wrapped['data']['email'])->toBe($user->email);
});
