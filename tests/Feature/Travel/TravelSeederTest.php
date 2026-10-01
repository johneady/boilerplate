<?php

use App\Models\Destination;
use App\Models\Tour;
use App\Models\TourDeparture;
use App\Models\TripInquiry;
use App\Settings\SettingKey;
use App\Settings\Settings;
use Database\Seeders\TravelSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('public');
});

test('it seeds the agency, its tours with upcoming dates and sample requests', function () {
    $this->seed(TravelSeeder::class);

    expect(app(Settings::class)->businessName())->toBe('Wanderlight Travel')
        ->and(app(Settings::class)->boolean(SettingKey::BlogEnabled))->toBeFalse()
        ->and(Destination::count())->toBe(8)
        ->and(Tour::count())->toBe(10)
        ->and(TourDeparture::where('starts_on', '>', now())->count())->toBe(40)
        ->and(TripInquiry::count())->toBeGreaterThan(0);

    Storage::disk('public')->assertExists(Destination::firstWhere('slug', 'iceland')->image_path);
});

test('running it again changes nothing', function () {
    $this->seed(TravelSeeder::class);
    Tour::firstWhere('slug', 'iceland-ring-road-adventure')->update(['name' => 'Renamed by the owner']);

    $this->seed(TravelSeeder::class);

    expect(Tour::count())->toBe(10)
        ->and(TourDeparture::count())->toBe(40)
        ->and(Tour::firstWhere('slug', 'iceland-ring-road-adventure')->name)->toBe('Renamed by the owner');
});

test('the seeder uses no factory, which production has no faker for', function () {
    expect(file_get_contents(base_path('database/seeders/TravelSeeder.php')))->not->toContain('factory(');
});
