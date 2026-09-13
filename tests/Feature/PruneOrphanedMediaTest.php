<?php

use App\Models\Media;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

test('it deletes an unattached row past the grace period, and its files', function () {
    $media = Media::factory()->create([
        'model_type' => null,
        'model_id' => null,
        'created_at' => now()->subDays(2),
    ]);

    Storage::disk('public')->put($media->path.'/thumb.webp', 'x');

    $this->artisan('app:prune-orphaned-media')->assertSuccessful();

    expect(Media::query()->count())->toBe(0);
    Storage::disk('public')->assertMissing($media->path.'/thumb.webp');
});

test('it keeps an unattached row still inside the grace period', function () {
    // The row is written before its owner exists, so a fresh unattached row is
    // indistinguishable from a create form still open in somebody's browser.
    Media::factory()->create([
        'model_type' => null,
        'model_id' => null,
        'created_at' => now()->subMinutes(5),
    ]);

    $this->artisan('app:prune-orphaned-media')->assertSuccessful();

    expect(Media::query()->count())->toBe(1);
});

test('it never touches a row that still has an owner', function () {
    $user = User::factory()->create();

    Media::factory()->create([
        'model_type' => $user->getMorphClass(),
        'model_id' => $user->getKey(),
        'created_at' => now()->subYear(),
    ]);

    $this->artisan('app:prune-orphaned-media')->assertSuccessful();

    expect(Media::query()->count())->toBe(1);
});

test('the grace period can be overridden for a one-off sweep', function () {
    Media::factory()->create([
        'model_type' => null,
        'model_id' => null,
        'created_at' => now()->subHours(2),
    ]);

    $this->artisan('app:prune-orphaned-media', ['--hours' => 1])->assertSuccessful();

    expect(Media::query()->count())->toBe(0);
});

test('a zero retention disables pruning entirely', function () {
    Media::factory()->create([
        'model_type' => null,
        'model_id' => null,
        'created_at' => now()->subYear(),
    ]);

    $this->artisan('app:prune-orphaned-media', ['--hours' => 0])
        ->expectsOutputToContain('disabled')
        ->assertSuccessful();

    expect(Media::query()->count())->toBe(1);
});

test('it deletes past a single chunk', function () {
    Media::factory()->count(150)->create([
        'model_type' => null,
        'model_id' => null,
        'created_at' => now()->subDays(2),
    ]);

    // chunkById rather than chunk: rows are deleted as it goes, so an
    // offset-based chunk would skip every second page.
    $this->artisan('app:prune-orphaned-media')->assertSuccessful();

    expect(Media::query()->count())->toBe(0);
});
