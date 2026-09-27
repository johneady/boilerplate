<?php

use App\Auth\Role;
use App\Filament\Resources\Categories\Pages\ManageCategories;
use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use App\Settings\SettingKey;
use App\Settings\Settings;
use Livewire\Livewire;

beforeEach(function (): void {
    app(Settings::class)->set(SettingKey::BlogEnabled, true);

    $this->admin = User::factory()->admin()->create();
});

test('an administrator can reach the categories screen', function () {
    $this->actingAs($this->admin)
        ->get('/admin/categories')
        ->assertSuccessful();
});

test('a bookkeeper cannot reach the categories screen', function () {
    $this->actingAs(User::factory()->role(Role::Bookkeeper)->create())
        ->get('/admin/categories')
        ->assertForbidden();
});

test('an editor can create a category', function () {
    $this->actingAs(User::factory()->role(Role::Editor)->create());

    Livewire::test(ManageCategories::class)
        ->callAction('create', ['name' => 'Guides', 'slug' => 'guides'])
        ->assertHasNoActionErrors();

    expect(Category::whereSlug('guides')->sole()->name)->toBe('Guides');
});

test('a category\'s post count is offered to the table', function () {
    $category = Category::factory()->create();
    Post::factory()->count(2)->for($category, 'category')->create();

    $this->actingAs($this->admin);

    Livewire::test(ManageCategories::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords(collect([$category]));
});

test('deleting a category leaves its posts in place', function () {
    $category = Category::factory()->create();
    $post = Post::factory()->for($category, 'category')->create();

    $this->actingAs($this->admin);

    Livewire::test(ManageCategories::class)
        ->callTableAction('delete', $category)
        ->assertHasNoTableActionErrors();

    // nullOnDelete, not cascade: the posts outlive their grouping.
    expect($post->refresh()->category_id)->toBeNull();
});
