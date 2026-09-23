<?php

use App\Filament\Resources\Packages\Pages\ManagePackages;
use App\Models\Package;
use App\Models\User;
use App\Shop\Region;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
});

test('an administrator can reach the packages screen', function () {
    $this->actingAs($this->admin)
        ->get('/admin/packages')
        ->assertSuccessful();
});

test('an ordinary user cannot reach the packages screen', function () {
    $this->actingAs(User::factory()->create())
        ->get('/admin/packages')
        ->assertForbidden();
});

test('an administrator can create a package with its price in dollars', function () {
    $this->actingAs($this->admin);

    Livewire::test(ManagePackages::class)
        ->callAction('create', [
            'title' => 'Faroe Sea Stacks',
            'slug' => 'faroe-sea-stacks',
            'location' => 'Vestmanna',
            'country' => 'Faroe Islands',
            'region' => Region::Europe->value,
            'price_cents' => '149.50',
            'summary' => 'Sea stacks and bird cliffs from above.',
            'resolution' => '4K UHD',
            'frame_rate' => 30,
            'clip_count' => 6,
            'duration_seconds' => 240,
            'stock' => '',
            'is_active' => true,
        ])
        ->assertHasNoActionErrors();

    $package = Package::sole();

    expect($package->price_cents)->toBe(14950)
        ->and($package->stock)->toBeNull()
        ->and($package->is_active)->toBeTrue();
});

test('stock is edited inline, and a blank cell means unlimited', function () {
    $package = Package::factory()->active()->limited(3)->create();

    $this->actingAs($this->admin);

    Livewire::test(ManagePackages::class)
        ->call('updateTableColumnState', 'stock', (string) $package->id, '10');

    expect($package->fresh()->stock)->toBe(10);

    Livewire::test(ManagePackages::class)
        ->call('updateTableColumnState', 'stock', (string) $package->id, '');

    expect($package->fresh()->stock)->toBeNull();
});
