<?php

use App\Auth\Role;
use App\Filament\Resources\Tags\Pages\ManageTags;
use App\Models\Tag;
use App\Models\User;
use App\Settings\SettingKey;
use App\Settings\Settings;
use Livewire\Livewire;

beforeEach(function (): void {
    app(Settings::class)->set(SettingKey::BlogEnabled, true);

    $this->admin = User::factory()->admin()->create();
});

test('an administrator can reach the tags screen', function () {
    $this->actingAs($this->admin)
        ->get('/admin/tags')
        ->assertSuccessful();
});

test('a bookkeeper cannot reach the tags screen', function () {
    $this->actingAs(User::factory()->role(Role::Bookkeeper)->create())
        ->get('/admin/tags')
        ->assertForbidden();
});

test('an editor can create a tag', function () {
    $this->actingAs(User::factory()->role(Role::Editor)->create());

    Livewire::test(ManageTags::class)
        ->callAction('create', ['name' => 'Tips', 'slug' => 'tips'])
        ->assertHasNoActionErrors();

    expect(Tag::whereSlug('tips')->sole()->name)->toBe('Tips');
});

test('an administrator can delete a tag', function () {
    $tag = Tag::factory()->create();

    $this->actingAs($this->admin);

    Livewire::test(ManageTags::class)
        ->callTableAction('delete', $tag)
        ->assertHasNoTableActionErrors();

    expect(Tag::query()->exists())->toBeFalse();
});
