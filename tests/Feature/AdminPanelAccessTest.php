<?php

use App\Models\User;

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

test('admins are redirected to the admin panel after logging in', function () {
    $user = User::factory()->admin()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(Filament\Facades\Filament::getPanel('admin')->getUrl());
});

test('non-admins are redirected to the dashboard after logging in', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect('/dashboard');
});
