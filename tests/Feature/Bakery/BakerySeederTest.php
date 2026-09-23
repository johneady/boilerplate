<?php

use App\Models\MenuItem;
use App\Models\OrderInquiry;
use App\Models\Page;
use App\Settings\SettingKey;
use App\Settings\Settings;
use Database\Seeders\BakerySeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('public');
});

test('the demo bakery is seeded with its menu, photos, pages and sample orders', function () {
    $this->seed(BakerySeeder::class);

    expect(MenuItem::count())->toBe(14)
        ->and(OrderInquiry::count())->toBeGreaterThan(0)
        ->and(Page::query()->where('slug', 'about')->value('title'))->toBe('About us')
        ->and(app(Settings::class)->businessName())->toBe('Hearth & Honey Bakery');

    MenuItem::all()->each(fn (MenuItem $item) => Storage::disk('public')->assertExists((string) $item->image_path));
});

test('re-seeding keeps the owner\'s edits and adds no duplicate orders', function () {
    $this->seed(BakerySeeder::class);

    MenuItem::query()->where('slug', 'cinnamon-rolls')->update(['price_cents' => 2600]);
    $orders = OrderInquiry::count();

    $this->seed(BakerySeeder::class);

    expect(MenuItem::count())->toBe(14)
        ->and(MenuItem::query()->where('slug', 'cinnamon-rolls')->value('price_cents'))->toBe(2600)
        ->and(OrderInquiry::count())->toBe($orders);
});

test('the bakery brand is kept when the generic settings seeder runs after it', function () {
    $this->seed();

    expect(app(Settings::class)->string(SettingKey::BusinessEmail))->toBe('hello@hearthandhoney.example');
});

/**
 * The seeder runs on every boot of the --no-dev production image, where
 * faker does not exist. See .ai/rules/seeders.md.
 */
test('the bakery seeder does not use a factory', function () {
    expect(file_get_contents(database_path('seeders/BakerySeeder.php')))->not->toContain('factory(');
});
