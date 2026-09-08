<?php

use App\Models\User;

test('the home page renders', function () {
    $this->get('/')
        ->assertSuccessful()
        ->assertSee('Boilerplate Industries')
        ->assertSee('We make the thing that holds the other things.');
});

test('the home page offers login and register to guests', function () {
    $this->get('/')
        ->assertSee(route('login'))
        ->assertSee(route('register'));
});

test('the home page links non-admins to the dashboard, not the panel', function () {
    $this->actingAs(User::factory()->create())
        ->get('/')
        ->assertSee(route('dashboard'))
        ->assertDontSee('Admin');
});

test('the home page links admins to the panel, not the dashboard', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/')
        ->assertSee('Admin')
        ->assertDontSee(route('dashboard'));
});

test('the auth pages use the split layout with the backdrop image', function () {
    $this->get(route('login'))
        ->assertSuccessful()
        ->assertSee('images/auth/backdrop.svg')
        ->assertSee('Built for the work that comes next.');
});
