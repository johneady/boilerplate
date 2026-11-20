<?php

use App\Auth\Role;
use App\Filament\Pages\Documentation;
use App\Models\User;

test('the overview is shown first, with every guide in the menu', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin/documentation')
        ->assertSuccessful()
        ->assertSee('Boilerplate documentation')
        ->assertSeeInOrder(['Overview', 'Installing and updating', 'Changelog']);
});

test('panel staff who are not administrators can read the guides too', function () {
    $this->actingAs(User::factory()->role(Role::Editor)->create())
        ->get('/admin/documentation?guide=installation-shared-hosting')
        ->assertSuccessful();
});

test('people outside the admin panel cannot read them', function () {
    $this->actingAs(User::factory()->create())
        ->get('/admin/documentation')
        ->assertForbidden();
});

test('a guide is rendered with ids on its headings so fragment links land', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin/documentation?guide=installation-shared-hosting')
        ->assertSuccessful()
        ->assertSee('<h2 id="updating">', escape: false)
        ->assertSee('href="#the-cron-job"', escape: false);
});

test('links between guides open them on this page', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin/documentation')
        ->assertSee('href="'.e(Documentation::getUrl(['guide' => 'installation-shared-hosting'])).'"', escape: false)
        ->assertSee('href="'.e(Documentation::getUrl(['guide' => 'installation-shared-hosting'])).'#updating"', escape: false)
        ->assertSee('href="'.e(Documentation::getUrl(['guide' => 'changelog'])).'"', escape: false)
        ->assertDontSee('.md"', escape: false);
});

test('the changelog is one of the guides', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin/documentation?guide=changelog')
        ->assertSuccessful()
        ->assertSee('<h1 id="changelog">', escape: false);
});

test('an unknown guide shows the overview', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin/documentation?guide=../../.env')
        ->assertSuccessful()
        ->assertSee('Boilerplate documentation');
});

test('a malformed guide parameter shows the overview', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin/documentation?guide[]=installation-shared-hosting')
        ->assertSuccessful()
        ->assertSee('Boilerplate documentation');
});
