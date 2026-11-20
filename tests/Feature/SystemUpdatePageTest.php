<?php

use App\Auth\Role;
use App\Filament\Pages\SystemUpdate;
use App\Models\User;

test('an administrator sees the current version and the updater launch', function () {
    config(['app.version' => '4.5.6']);

    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin/system-update')
        ->assertSuccessful()
        ->assertSee('v4.5.6')
        ->assertSee(route('updater.launch'));
});

/**
 * An update replaces the application's code, and the toolkit's launch route
 * refuses anyone without is_admin -- so the page must not be offered to a
 * role that merely reaches the panel.
 */
test('panel staff who are not administrators cannot reach it', function (Role $role) {
    $this->actingAs(User::factory()->role($role)->create())
        ->get('/admin/system-update')
        ->assertForbidden();
})->with([Role::Editor, Role::Bookkeeper, Role::Manager]);

test('only administrators may launch the updater', function () {
    $this->actingAs(User::factory()->role(Role::Manager)->create())
        ->post(route('updater.launch'))
        ->assertForbidden();
});

test('the page is gone where the updater is switched off', function () {
    config(['updates.updater.enabled' => false]);

    $this->actingAs(User::factory()->admin()->create());

    expect(SystemUpdate::canAccess())->toBeFalse();
});
