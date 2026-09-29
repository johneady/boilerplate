<?php

use App\Models\Perfume;
use App\Models\PerfumeImport;
use Database\Seeders\PerfumeSeeder;
use Illuminate\Support\Facades\DB;

test('the seeder builds the catalogue through two dated imports plus community history', function () {
    $this->seed(PerfumeSeeder::class);

    $imports = PerfumeImport::orderBy('finished_at')->get();

    expect(Perfume::count())->toBe(47)
        ->and($imports->pluck('file_name')->all())->toBe(['lovable-export.csv', 'refresh-2026-08.csv'])
        ->and($imports->every(fn (PerfumeImport $import): bool => $import->finished_at->isPast()))->toBeTrue()
        ->and(DB::table('perfume_follows')->count())->toBeGreaterThan(200)
        ->and(DB::table('perfume_views')->where('viewed_on', now()->toDateString())->exists())->toBeTrue();
});

test('the seeder does not run twice', function () {
    $this->seed(PerfumeSeeder::class);
    $this->seed(PerfumeSeeder::class);

    expect(PerfumeImport::count())->toBe(2);
});
