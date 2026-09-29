<?php

use App\Auth\Role;
use App\Livewire\Perfumes\Browse;
use App\Livewire\Perfumes\FollowButton;
use App\Models\Brand;
use App\Models\Perfume;
use App\Models\PerfumeView;
use App\Models\User;
use App\Perfumes\Enums\Family;
use Livewire\Livewire;

test('a perfume page shows its notes and counts a human visit', function () {
    $perfume = Perfume::factory()->create(['name' => 'Shalimar', 'top_notes' => ['Bergamot'], 'base_notes' => ['Opoponax']]);

    $this->get(route('perfumes.show', $perfume))
        ->assertSuccessful()
        ->assertSee('Shalimar')
        ->assertSee('Opoponax');

    $this->get(route('perfumes.show', $perfume))->assertSuccessful();

    expect(PerfumeView::sole()->views)->toBe(2);
});

test('crawler visits are not counted as views', function () {
    $perfume = Perfume::factory()->create();

    $this->withHeader('User-Agent', 'Mozilla/5.0 (compatible; Googlebot/2.1)')
        ->get(route('perfumes.show', $perfume))
        ->assertSuccessful();

    expect(PerfumeView::count())->toBe(0);
});

test('old Lovable links redirect permanently to the new perfume page', function () {
    $perfume = Perfume::factory()->create(['external_id' => '3f2a-old-id', 'slug' => 'guerlain-shalimar']);

    $this->get('/perfume/3f2a-old-id')->assertRedirect(route('perfumes.show', $perfume))->assertStatus(301);
    $this->get('/perfume/unknown')->assertNotFound();
});

test('the catalogue searches notes and filters by family and house', function () {
    $guerlain = Brand::factory()->create(['name' => 'Guerlain', 'slug' => 'guerlain']);
    Perfume::factory()->for($guerlain)->create(['name' => 'Shalimar', 'family' => Family::Amber, 'base_notes' => ['Vanilla']]);
    Perfume::factory()->for($guerlain)->create(['name' => 'Vetiver', 'family' => Family::Woody, 'base_notes' => ['Tobacco']]);
    Perfume::factory()->create(['name' => 'Angel', 'family' => Family::Gourmand, 'base_notes' => ['Vanilla']]);

    Livewire::test(Browse::class)
        ->set('search', 'vanilla')
        ->assertSee('Shalimar')->assertSee('Angel')->assertDontSee('Vetiver')
        ->set('family', 'amber')
        ->assertSee('Shalimar')->assertDontSee('Angel')
        ->call('clearFilters')
        ->set('brand', 'guerlain')
        ->assertSee('Vetiver')->assertDontSee('Angel');
});

test('a member can follow and unfollow a perfume', function () {
    $perfume = Perfume::factory()->create();
    $member = User::factory()->create();

    Livewire::actingAs($member)
        ->test(FollowButton::class, ['perfume' => $perfume])
        ->call('toggle')
        ->assertSet('following', true)
        ->assertSet('followers', 1)
        ->call('toggle')
        ->assertSet('following', false)
        ->assertSet('followers', 0);
});

test('a guest who presses follow is sent to sign up', function () {
    Livewire::test(FollowButton::class, ['perfume' => Perfume::factory()->create()])
        ->call('toggle')
        ->assertRedirect(route('register'));
});

test('the open stats page counts members but not staff', function () {
    User::factory()->count(3)->create();
    User::factory()->role(Role::Editor)->create();
    User::factory()->admin()->create();

    $response = $this->get(route('stats'))->assertSuccessful();

    expect($response->viewData('members'))->toBe(3);
});

test('the member dashboard lists the perfumes they follow', function () {
    $member = User::factory()->create();
    $member->followedPerfumes()->attach(Perfume::factory()->create(['name' => 'Philosykos']), ['created_at' => now()]);

    $this->actingAs($member)->get(route('dashboard'))->assertSuccessful()->assertSee('Philosykos');
});

test('perfume admin screens are open to curators and closed to members', function (string $path) {
    $this->actingAs(User::factory()->role(Role::Editor)->create())->get($path)->assertSuccessful();
    $this->actingAs(User::factory()->create())->get($path)->assertForbidden();
})->with(['/admin/perfumes', '/admin/data-refresh']);
