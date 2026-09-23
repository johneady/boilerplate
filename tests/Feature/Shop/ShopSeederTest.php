<?php

use App\Models\Order;
use App\Models\Package;
use Database\Seeders\ShopSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('public');
});

test('it seeds the catalogue with its cover images and sample orders', function () {
    $this->seed(ShopSeeder::class);

    expect(Package::query()->active()->count())->toBe(8)
        ->and(Order::count())->toBeGreaterThan(0);

    Package::all()->each(fn (Package $package) => Storage::disk('public')->assertExists($package->image_path));
});

test('reseeding keeps an administrator\'s edits and adds no more orders', function () {
    $this->seed(ShopSeeder::class);

    $orders = Order::count();
    Package::where('slug', 'lake-bled')->update(['price_cents' => 100, 'stock' => 4]);

    $this->seed(ShopSeeder::class);

    $bled = Package::where('slug', 'lake-bled')->sole();

    expect(Package::count())->toBe(8)
        ->and(Order::count())->toBe($orders)
        ->and($bled->price_cents)->toBe(100)
        ->and($bled->stock)->toBe(4);
});

test('the seeder does not use factories, which the production image cannot run', function () {
    expect(file_get_contents(database_path('seeders/ShopSeeder.php')))->not->toContain('factory(');
});
