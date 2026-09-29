<?php

use App\Models\Perfume;
use App\Models\PerfumeImport;
use App\Models\User;
use App\Perfumes\Enums\Concentration;
use App\Perfumes\Enums\Family;
use App\Perfumes\Enums\ImportStatus;
use App\Perfumes\PerfumeImporter;
use Illuminate\Support\Facades\Http;

function perfumeCsv(string $contents): string
{
    $path = tempnam(sys_get_temp_dir(), 'perfumes');
    file_put_contents($path, $contents);

    return $path;
}

test('a new row creates a perfume and its brand from export-style headers', function () {
    $path = perfumeCsv(<<<'CSV'
        id,brand,country,name,year,concentration,gender,family,perfumer,top_notes,heart_notes,base_notes
        abc-1,Guerlain,France,Shalimar,1925,EDP,Women,Oriental,Jacques Guerlain,Bergamot|Lemon,Iris;Jasmine,"Vanilla, Tonka bean"
        CSV);

    $import = app(PerfumeImporter::class)->import($path, 'export.csv');

    $perfume = Perfume::with('brand')->sole();

    expect($import->status)->toBe(ImportStatus::Completed)
        ->and($import->rows_created)->toBe(1)
        ->and($perfume->external_id)->toBe('abc-1')
        ->and($perfume->slug)->toBe('guerlain-shalimar')
        ->and($perfume->brand->name)->toBe('Guerlain')
        ->and($perfume->brand->country)->toBe('France')
        ->and($perfume->concentration)->toBe(Concentration::EauDeParfum)
        ->and($perfume->family)->toBe(Family::Amber)
        ->and($perfume->heart_notes)->toBe(['Iris', 'Jasmine'])
        ->and($perfume->base_notes)->toBe(['Vanilla', 'Tonka bean']);
});

test('a refresh updates changed rows in place and counts identical rows as unchanged', function () {
    $original = Perfume::factory()->create(['external_id' => 'p-1', 'name' => 'Old Name', 'slug' => 'kept-slug', 'release_year' => 2001]);
    $follower = User::factory()->create();
    $original->followers()->attach($follower, ['created_at' => now()]);
    Perfume::factory()->for($original->brand)->create([
        'external_id' => 'p-2', 'name' => 'Same', 'release_year' => null, 'concentration' => null, 'gender' => null,
        'family' => null, 'perfumer' => null, 'top_notes' => null, 'heart_notes' => null, 'base_notes' => null, 'description' => null,
    ]);
    $brand = $original->brand->name;

    $import = app(PerfumeImporter::class)->import(perfumeCsv(<<<CSV
        external_id,brand,name,release_year
        p-1,"{$brand}",New Name,2001
        p-2,"{$brand}",Same,
        CSV), 'refresh.csv');

    $original->refresh();

    expect([$import->rows_created, $import->rows_updated, $import->rows_unchanged])->toBe([0, 1, 1])
        ->and($original->name)->toBe('New Name')
        ->and($original->slug)->toBe('kept-slug')
        ->and($original->followers()->count())->toBe(1)
        ->and(Perfume::count())->toBe(2);
});

test('bad rows are skipped and reported by line while good rows import', function () {
    $import = app(PerfumeImporter::class)->import(perfumeCsv(<<<'CSV'
        external_id,brand,name,release_year,family
        good,Diptyque,Philosykos,1996,Woody
        typo,Chanel,Chance,20O2,Floral
        ,Chanel,No Id,2002,
        odd,Chanel,Odd,2002,Seaweed
        CSV), 'mixed.csv');

    expect($import->status)->toBe(ImportStatus::CompletedWithErrors)
        ->and($import->rows_total)->toBe(4)
        ->and($import->rows_created)->toBe(1)
        ->and($import->rows_failed)->toBe(3)
        ->and(array_keys($import->errors))->toEqualCanonicalizing([3, 4, 5])
        ->and(Perfume::pluck('name')->all())->toBe(['Philosykos']);
});

test('a file without the required columns fails without importing anything', function () {
    $import = app(PerfumeImporter::class)->import(perfumeCsv("brand,name\nChanel,N°5\n"), 'wrong.csv');

    expect($import->status)->toBe(ImportStatus::Failed)
        ->and($import->errors['header'])->toContain('external_id')
        ->and(Perfume::count())->toBe(0);
});

test('two perfumes that slug the same get distinct slugs', function () {
    app(PerfumeImporter::class)->import(perfumeCsv(<<<'CSV'
        external_id,brand,name
        a,Dior,Sauvage
        b,Dior,Sauvage
        CSV), 'dupes.csv');

    expect(Perfume::orderBy('id')->pluck('slug')->all())->toBe(['dior-sauvage', 'dior-sauvage-2']);
});

test('the bundled seed files import cleanly', function (string $file, int $failed) {
    $import = app(PerfumeImporter::class)->import(database_path("seeders/data/{$file}"), $file);

    expect($import->rows_failed)->toBe($failed);
})->with([
    ['lovable-export.csv', 0],
    ['refresh-2026-08.csv', 0],
    ['sample-refresh.csv', 1],
]);

test('the import command pulls a feed URL and reports what changed', function () {
    Http::fake(['feeds.example/*' => Http::response("external_id,brand,name\nf-1,Byredo,Gypsy Water\n")]);

    $this->artisan('perfumes:import', ['source' => 'https://feeds.example/perfumes.csv'])
        ->expectsOutputToContain('Created')
        ->assertSuccessful();

    expect(Perfume::sole()->name)->toBe('Gypsy Water')
        ->and(PerfumeImport::sole()->file_name)->toBe('perfumes.csv');
});

test('the scheduled import is a no-op without a configured feed', function () {
    config(['perfumes.feed_url' => null]);

    $this->artisan('perfumes:import')->assertSuccessful();

    expect(PerfumeImport::count())->toBe(0);
});
