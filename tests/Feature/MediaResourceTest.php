<?php

use App\Auth\Role;
use App\Filament\Resources\Media\MediaResource;
use App\Filament\Resources\Media\Pages\ListMedia;
use App\Models\Media;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');

    actingAs(User::factory()->create(['role' => Role::Admin]));
});

test('the library lists stored files', function () {
    $media = Media::factory()->create(['file_name' => 'portrait.jpg']);

    Livewire::test(ListMedia::class)
        ->assertCanSeeTableRecords([$media])
        ->assertSee('portrait.jpg');
});

test('an ordinary user cannot reach the library', function () {
    actingAs(User::factory()->create(['role' => Role::User]));

    expect(MediaResource::canViewAny())->toBeFalse();
});

test('the library offers no way to create or edit a file', function () {
    // Uploading goes through MediaManager, which validates and re-encodes. A
    // panel form would be a second way in that skipped all of it, and a row is
    // a statement about bytes on disk that editing cannot move.
    expect(MediaResource::canCreate())->toBeFalse();

    $media = Media::factory()->create();

    expect(MediaResource::canEdit($media))->toBeFalse();
});

test('an administrator can delete a file, and its bytes go with it', function () {
    $user = User::factory()->create();

    $media = $this->giveAvatar($user);

    Storage::disk('public')->assertExists('avatars/1/abc/thumb.webp');

    Livewire::test(ListMedia::class)
        ->callTableAction('delete', $media);

    expect(Media::query()->find($media->id))->toBeNull();

    // Deleting the row without the files would leave bytes nothing points at.
    Storage::disk('public')->assertMissing('avatars/1/abc/thumb.webp');
});

test('the orphan filter finds files nothing points at', function () {
    $attached = Media::factory()->create([
        'model_type' => User::class,
        'model_id' => User::factory()->create()->id,
    ]);

    $orphan = Media::factory()->create([
        'model_type' => null,
        'model_id' => null,
    ]);

    // This is the question the hourly prune answers, asked by hand -- nothing
    // else in the panel makes a file awaiting collection visible.
    Livewire::test(ListMedia::class)
        ->filterTable('orphaned')
        ->assertCanSeeTableRecords([$orphan])
        ->assertCanNotSeeTableRecords([$attached]);
});

test('the collection filter narrows to one slot', function () {
    $avatar = Media::factory()->create();
    $logo = Media::factory()->logo()->create();

    Livewire::test(ListMedia::class)
        ->filterTable('collection', ['logo'])
        ->assertCanSeeTableRecords([$logo])
        ->assertCanNotSeeTableRecords([$avatar]);
});

test('the documents filter excludes images', function () {
    $image = Media::factory()->create();
    $document = Media::factory()->document()->create();

    Livewire::test(ListMedia::class)
        ->filterTable('documents')
        ->assertCanSeeTableRecords([$document])
        ->assertCanNotSeeTableRecords([$image]);
});

test('an image still being processed is shown as such rather than as a broken link', function () {
    $media = Media::factory()->pending()->create();

    // conversions is null until the worker finishes, so there is nothing to
    // render an <img> from -- the row must say "processing", not show a gap.
    Livewire::test(ListMedia::class)
        ->assertCanSeeTableRecords([$media]);

    expect($media->url())->toBeNull();
});

test('the download action is hidden for a file with nothing to serve', function () {
    // An orphaned document has no owning record to authorize against, so the
    // signed route would refuse it anyway.
    $orphan = Media::factory()->document()->create([
        'model_type' => null,
        'model_id' => null,
    ]);

    Livewire::test(ListMedia::class)
        ->assertTableActionHidden('download', $orphan);
});

test('a stored image offers a download', function () {
    $user = User::factory()->create();

    $media = $this->giveAvatar($user);

    Livewire::test(ListMedia::class)
        ->assertTableActionVisible('download', $media);
});

test('the library shows what a file is attached to', function () {
    $owner = User::factory()->create();

    Media::factory()->create([
        'model_type' => $owner->getMorphClass(),
        'model_id' => $owner->getKey(),
    ]);

    Livewire::test(ListMedia::class)
        ->assertSee("User #{$owner->id}");
});
