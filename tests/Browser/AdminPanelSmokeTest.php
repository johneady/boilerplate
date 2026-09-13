<?php

use App\Models\User;

/*
 * The admin panel is the densest JavaScript in the application -- Filament's
 * own bundle plus Livewire plus Alpine -- and the feature suite asserts only
 * that its pages return 200. A panel that boots with a JavaScript error still
 * returns 200 while being unusable, which is exactly what a dependency bump
 * tends to cause.
 */

test('the admin dashboard renders without javascript errors', function () {
    $this->actingAs(User::factory()->admin()->create());

    $page = visit('/admin');

    $page->assertSee('Overview')
        ->assertNoJavaScriptErrors();
});

test('the settings page renders its tabs', function () {
    $this->actingAs(User::factory()->admin()->create());

    $page = visit('/admin/settings');

    $page->assertSee('Business details')
        ->assertSee('Diagnostics')
        ->assertNoJavaScriptErrors();
});

/**
 * The Diagnostics tab's panel is rendered by Filament inside the tab, so this
 * is the only level at which "clicking the tab shows the report" is actually
 * proven -- the Livewire test can see the markup without it ever being
 * reachable by a click.
 */
test('the diagnostics tab shows its report when clicked', function () {
    $this->actingAs(User::factory()->admin()->create());

    $page = visit('/admin/settings');

    $page->click('Diagnostics')
        ->assertSee('Configuration audit')
        ->assertNoJavaScriptErrors();
});

test('the users list renders', function () {
    $this->actingAs(User::factory()->admin()->create(['name' => 'Ada Lovelace']));

    $page = visit('/admin/users');

    $page->assertSee('Ada Lovelace')
        ->assertNoJavaScriptErrors();
});
