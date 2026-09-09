<?php

use App\Models\User;
use Filament\Auth\Http\Responses\Contracts\LogoutResponse;
use Filament\Support\Enums\Width;

test('the panel does not register a login page of its own', function () {
    expect(Route::has('filament.admin.auth.login'))->toBeFalse();
});

test('guests are redirected to the application login page', function () {
    $this->get('/admin')->assertRedirect(route('login'));
});

test('non-admins are forbidden from the admin panel', function () {
    $this->actingAs(User::factory()->create())
        ->get('/admin')
        ->assertForbidden();
});

test('admins may access the admin panel', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin')
        ->assertSuccessful();
});

test('is_admin cannot be mass assigned', function () {
    $user = User::create([
        'name' => 'Mass Assigned',
        'email' => 'mass@example.com',
        'password' => 'password',
        'is_admin' => true,
    ]);

    expect($user->fresh()->is_admin)->toBeFalse();
});

test('logging out of the panel returns to the home page', function () {
    $response = app(LogoutResponse::class)
        ->toResponse(request());

    expect($response->getTargetUrl())->toBe(url('/'));
});

test('admins are redirected to the admin panel after logging in', function () {
    $user = User::factory()->admin()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(Filament\Facades\Filament::getPanel('admin')->getUrl());
});

test('admins reach the panel even after being bounced off the dashboard', function () {
    $user = User::factory()->admin()->create();

    $this->get('/dashboard')->assertRedirect(route('login'));

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(Filament\Facades\Filament::getPanel('admin')->getUrl());
});

test('admins still land on a deliberate deep link captured before login', function () {
    $user = User::factory()->admin()->create();

    $this->get(route('profile.edit'))->assertRedirect(route('login'));

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('profile.edit'));
});

test('non-admins bounced off the dashboard still land there after logging in', function () {
    $user = User::factory()->create();

    $this->get('/dashboard')->assertRedirect(route('login'));

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect('/dashboard');
});

test('non-admins are redirected to the dashboard after logging in', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect('/dashboard');
});

test('the dashboard has no stock Filament widgets', function () {
    $html = $this->actingAs(User::factory()->admin()->create())
        ->get('/admin')
        ->assertSuccessful()
        ->getContent();

    expect($html)->not->toContain('filament-widgets-account-widget')
        ->and($html)->not->toContain('filament-widgets-filament-info-widget');
});

test('the panel content spans the full width', function () {
    expect(Filament\Facades\Filament::getPanel('admin')->getMaxContentWidth())
        ->toBe(Width::Full);
});

test('the panel navigation links back to the website', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin')
        ->assertSuccessful()
        ->assertSee('Return to website');
});
