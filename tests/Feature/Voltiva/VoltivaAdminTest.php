<?php

use App\Filament\Resources\Enquiries\EnquiryResource;
use App\Filament\Resources\Enquiries\Pages\ListEnquiries;
use App\Filament\Resources\Enquiries\Pages\ViewEnquiry;
use App\Filament\Resources\Vehicles\Pages\CreateVehicle;
use App\Models\Enquiry;
use App\Models\User;
use App\Models\Vehicle;
use App\Voltiva\EnquiryStatus;
use Livewire\Livewire;

test('administrators reach the cars, articles and enquiries screens', function (string $path) {
    $this->actingAs(User::factory()->admin()->create())
        ->get($path)
        ->assertSuccessful();
})->with(['/admin/vehicles', '/admin/vehicles/create', '/admin/articles', '/admin/articles/create', '/admin/enquiries']);

test('ordinary users cannot reach them', function (string $path) {
    $this->actingAs(User::factory()->create())
        ->get($path)
        ->assertForbidden();
})->with(['/admin/vehicles', '/admin/articles', '/admin/enquiries']);

/**
 * Every field the create form requires, as an editor would type them.
 *
 * @return array<string, mixed>
 */
function newCarFormData(array $overrides = []): array
{
    return [
        'name' => 'Voltiva Mar', 'slug' => 'voltiva-mar', 'category' => 'l7e', 'tagline' => 'Sea breeze, no fumes.',
        'summary' => 'A breezy two-seater.', 'price_cents' => '15990', 'monthly_from_cents' => '249',
        'top_speed_kmh' => 85, 'range_km' => 160, 'motor_kw' => 12, 'charge_hours' => 7.5,
        'battery_voltage' => 72, 'battery_capacity_ah' => 150, 'battery_kwh' => 10.8, 'battery_chemistry' => 'LiFePO4',
        'seats' => 2, 'length_mm' => 2500, 'width_mm' => 1450, 'height_mm' => 1550, 'kerb_weight_kg' => 450,
        'warranty_years' => 3, 'battery_warranty_years' => 8, 'is_published' => true,
        ...$overrides,
    ];
}

test('an editor adds a car, typing prices in euros', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(CreateVehicle::class)
        ->fillForm(newCarFormData())
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Vehicle::sole())
        ->price_cents->toBe(1599000)
        ->monthly_from_cents->toBe(24900);
});

test('a car cannot take a category listing address as its slug', function (string $slug) {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(CreateVehicle::class)
        ->fillForm(newCarFormData(['slug' => $slug]))
        ->call('create')
        ->assertHasFormErrors(['slug']);
})->with(['l6e', 'l7e']);

test('the sales team changes an enquiry status from the list', function () {
    $this->actingAs(User::factory()->admin()->create());
    $enquiry = Enquiry::factory()->create();

    Livewire::test(ListEnquiries::class)
        ->call('updateTableColumnState', 'status', (string) $enquiry->getKey(), 'contacted');

    expect($enquiry->refresh()->status)->toBe(EnquiryStatus::Contacted);
});

test('closing an enquiry stops its email sequence at once', function () {
    $this->actingAs(User::factory()->admin()->create());
    $enquiry = Enquiry::factory()->create();

    Livewire::test(ViewEnquiry::class, ['record' => $enquiry->getKey()])
        ->callAction('update', ['status' => 'won', 'staff_notes' => 'Collected on Friday.']);

    expect($enquiry->refresh())
        ->status->toBe(EnquiryStatus::Won)
        ->staff_notes->toBe('Collected on Friday.')
        ->next_follow_up_at->toBeNull();
});

test('the navigation badge counts enquiries nobody has contacted', function () {
    Enquiry::factory()->count(2)->create();
    Enquiry::factory()->create(['status' => EnquiryStatus::Contacted]);

    expect(EnquiryResource::getNavigationBadge())->toBe('2');
});

test('the export downloads the enquiries with customer-typed formulas neutralised', function () {
    $this->actingAs(User::factory()->admin()->create());
    Enquiry::factory()->create(['name' => 'Marta Pons', 'message' => '=HYPERLINK("http://evil.example")']);

    $download = Livewire::test(ListEnquiries::class)->callAction('export');

    $download->assertFileDownloaded('enquiries-'.now()->format('Y-m-d').'.csv');
    $csv = base64_decode($download->effects['download']['content']);
    expect($csv)->toContain('Marta Pons')
        ->toContain('"\'=HYPERLINK(""http://evil.example"")"')
        ->not->toContain(',"=HYPERLINK');
});
