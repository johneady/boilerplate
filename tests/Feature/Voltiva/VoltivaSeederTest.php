<?php

use App\Models\Article;
use App\Models\Enquiry;
use App\Models\Page;
use App\Models\Vehicle;
use Database\Seeders\VoltivaContentSeeder;
use Database\Seeders\VoltivaSeeder;
use Illuminate\Support\Facades\Storage;

afterEach(function (): void {
    // detectEnvironment() changes the environment container-wide, so the one
    // test that pretends to be production must not leak it into the next.
    app()->detectEnvironment(fn (): string => 'testing');
});

test('the demo seeds the range, the content and sample enquiries', function () {
    Storage::fake('public');

    $this->seed([VoltivaSeeder::class, VoltivaContentSeeder::class]);

    expect(Vehicle::query()->published()->count())->toBe(4)
        ->and(Article::query()->published()->count())->toBe(7)
        ->and(Enquiry::count())->toBe(7)
        ->and(Page::query()->where('slug', 'batteries-and-range')->exists())->toBeTrue();
    Storage::disk('public')->assertExists(['site/hero.webp', 'vehicles/terra.webp']);
});

test('reseeding keeps an editor\'s changes', function () {
    Storage::fake('public');
    $this->seed([VoltivaSeeder::class, VoltivaContentSeeder::class]);
    Vehicle::where('slug', 'voltiva-terra')->update(['price_cents' => 1299000]);

    $this->seed([VoltivaSeeder::class, VoltivaContentSeeder::class]);

    expect(Vehicle::count())->toBe(4)
        ->and(Vehicle::where('slug', 'voltiva-terra')->value('price_cents'))->toBe(1299000)
        ->and(Enquiry::count())->toBe(7);
});

test('the seeded sample enquiries do not mail their example addresses on the first run', function () {
    Storage::fake('public');
    $this->seed(VoltivaSeeder::class);

    expect(Enquiry::query()->followUpDue()->count())->toBe(0);
});

test('sample enquiries are not seeded into a production crm', function () {
    Storage::fake('public');
    app()->detectEnvironment(fn (): string => 'production');

    $this->artisan('db:seed', ['--class' => VoltivaSeeder::class, '--force' => true]);

    expect(Enquiry::count())->toBe(0)
        ->and(Vehicle::count())->toBe(4);
});

test('the voltiva seeders build without factories', function (string $file) {
    // They run inside the --no-dev image, which has no faker. See .ai/rules/seeders.md.
    expect(file_get_contents(database_path('seeders/'.$file)))->not->toContain('factory(');
})->with(['VoltivaSeeder.php', 'VoltivaContentSeeder.php']);
