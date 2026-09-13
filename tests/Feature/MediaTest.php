<?php

use App\Jobs\ProcessUploadedImage;
use App\Media\MediaCollection;
use App\Media\MediaManager;
use App\Models\Media;
use App\Models\Page;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
});

test('attaching an image stages it privately and queues processing', function () {
    Queue::fake();

    $user = User::factory()->create();

    $media = app(MediaManager::class)->attach(
        file: UploadedFile::fake()->image('portrait.jpg', 400, 400),
        collection: MediaCollection::Avatar,
        owner: $user,
    );

    // The unprocessed original must never reach the public disk: it still
    // carries whatever metadata the uploader sent.
    expect(Storage::disk('local')->allFiles('uploads/pending'))->toHaveCount(1);
    expect(Storage::disk('public')->allFiles())->toBeEmpty();

    expect($media->conversions)->toBeNull()
        ->and($media->model_id)->toBe($user->id)
        ->and($media->collection)->toBe('avatar');

    Queue::assertPushed(ProcessUploadedImage::class);
});

test('an image is unreadable until its conversions are written', function () {
    Queue::fake();

    $user = User::factory()->create();

    $media = app(MediaManager::class)->attach(
        file: UploadedFile::fake()->image('portrait.jpg', 400, 400),
        collection: MediaCollection::Avatar,
        owner: $user,
    );

    // The window between upload and the worker finishing. A URL here would
    // point at a file nothing has written.
    expect($media->url())->toBeNull()
        ->and($media->isImage())->toBeFalse();
});

test('a document is stored as uploaded on the private disk', function () {
    $user = User::factory()->create();

    $media = app(MediaManager::class)->attach(
        file: UploadedFile::fake()->create('contract.pdf', 16, 'application/pdf'),
        collection: MediaCollection::Attachment,
        owner: $user,
    );

    expect($media->disk)->toBe('local')
        ->and($media->conversions)->toBeNull()
        ->and($media->isPubliclyReadable())->toBeFalse()
        // Nothing re-encodes a document, so it is never served as a static URL.
        ->and($media->url())->toBeNull();

    Storage::disk('local')->assertExists($media->path);
    Storage::disk('public')->assertMissing($media->path);
});

test('a single collection replaces rather than accumulates', function () {
    Queue::fake();

    $user = User::factory()->create();

    app(MediaManager::class)->attach(
        file: UploadedFile::fake()->image('first.jpg', 400, 400),
        collection: MediaCollection::Avatar,
        owner: $user,
    );

    $second = app(MediaManager::class)->attach(
        file: UploadedFile::fake()->image('second.jpg', 400, 400),
        collection: MediaCollection::Avatar,
        owner: $user,
    );

    $user->unsetRelation('media');

    // Two attached avatars would mean rendering picks whichever sorted first.
    expect($user->getMedia(MediaCollection::Avatar)->pluck('id')->all())
        ->toBe([$second->id]);
});

test('a multiple collection accumulates and keeps its order', function () {
    $page = Page::factory()->create();

    foreach (['a.pdf', 'b.pdf', 'c.pdf'] as $name) {
        app(MediaManager::class)->attach(
            file: UploadedFile::fake()->create($name, 8, 'application/pdf'),
            collection: MediaCollection::Attachment,
            owner: $page,
        );
    }

    $page->unsetRelation('media');

    expect($page->getMedia(MediaCollection::Attachment)->pluck('file_name')->all())
        ->toBe(['a.pdf', 'b.pdf', 'c.pdf']);
});

test('an upload that fails validation is rejected before anything is stored', function () {
    $user = User::factory()->create();

    // A PDF offered to an image collection. `mimes` checks the file's
    // CONTENTS, so the collection's allow-list is what rejects it.
    expect(fn () => app(MediaManager::class)->attach(
        file: UploadedFile::fake()->create('payload.pdf', 8, 'application/pdf'),
        collection: MediaCollection::Avatar,
        owner: $user,
    ))->toThrow(ValidationException::class);

    // Nothing may reach the disk, and no row may claim it did.
    expect(Storage::disk('local')->allFiles())->toBeEmpty()
        ->and(Media::query()->count())->toBe(0);
});

test('an svg is refused even though it is an image', function () {
    $user = User::factory()->create();

    // An SVG is a scriptable document served from our own origin, so it stays
    // off the accepted list no matter that a browser renders it as a picture.
    expect(fn () => app(MediaManager::class)->attach(
        file: UploadedFile::fake()->createWithContent(
            'mark.svg',
            '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
        ),
        collection: MediaCollection::Avatar,
        owner: $user,
    ))->toThrow(ValidationException::class);
});

test('an uploaded file name cannot escape its directory', function () {
    $user = User::factory()->create();

    $media = app(MediaManager::class)->attach(
        file: UploadedFile::fake()->create('../../../.env', 8, 'application/pdf'),
        collection: MediaCollection::Attachment,
        owner: $user,
    );

    // The stored path is built from a uuid, never from the name the browser
    // sent -- and the name kept for display has its traversal stripped.
    expect($media->path)->toStartWith('attachments/')
        ->and($media->path)->not->toContain('..')
        ->and($media->file_name)->not->toContain('/');
});

test('a url is not offered for a file that is no longer on the disk', function () {
    $user = User::factory()->create();

    $media = $this->giveAvatar($user);

    // The row still names the conversion, but the file is gone -- a failed
    // deploy, a manual cleanup, a half-restored backup. The conversions map
    // records what processing WROTE and cannot know what was removed later.
    Storage::disk('public')->delete('avatars/1/abc/thumb.webp');

    // A URL here renders a broken image; null renders the initials fallback,
    // which is what every caller is written to expect.
    expect($media->url('thumb'))->toBeNull()
        ->and($media->url('full'))->not->toBeNull();
});

test('the first file in a collection sorts at zero', function () {
    $user = User::factory()->create();

    $first = app(MediaManager::class)->attach(
        file: UploadedFile::fake()->create('a.pdf', 8, 'application/pdf'),
        collection: MediaCollection::Attachment,
        owner: $user,
    );

    // max() over no rows is null, and (int) null + 1 would number the first
    // item as though something came before it.
    expect($first->sort_order)->toBe(0);
});

test('deleting a media row deletes the files it points at', function () {
    $user = User::factory()->create();

    $media = $this->giveAvatar($user);

    Storage::disk('public')->assertExists('avatars/1/abc/thumb.webp');

    $media->delete();

    Storage::disk('public')->assertMissing('avatars/1/abc/thumb.webp');
    Storage::disk('public')->assertMissing('avatars/1/abc/full.webp');
});

test('deleting a record deletes the files attached to it', function () {
    $user = User::factory()->create();

    $this->giveAvatar($user);

    $user->delete();

    // A row surviving its owner points at bytes nothing will ever serve, and
    // nothing would ever delete them.
    expect(Media::query()->count())->toBe(0);
    Storage::disk('public')->assertMissing('avatars/1/abc/thumb.webp');
});

test('clearing a collection leaves other collections alone', function () {
    $page = Page::factory()->create();

    app(MediaManager::class)->attach(
        file: UploadedFile::fake()->create('keep.pdf', 8, 'application/pdf'),
        collection: MediaCollection::Attachment,
        owner: $page,
    );

    $page->clearMedia(MediaCollection::Avatar);

    expect($page->getMedia(MediaCollection::Attachment))->toHaveCount(1);
});

test('a private file is served only to someone who may see its owner', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();

    $media = Media::factory()->document()->create([
        'model_type' => $owner->getMorphClass(),
        'model_id' => $owner->getKey(),
    ]);

    Storage::disk('local')->put($media->path, 'secret');

    // A signed link is a bearer token: without the policy check on top, anyone
    // holding the URL would get the file.
    actingAs($stranger)
        ->get($media->signedUrl())
        ->assertForbidden();

    actingAs($owner)
        ->get($media->signedUrl())
        ->assertOk();
});

test('an unsigned request for a private file is refused', function () {
    $owner = User::factory()->create();

    $media = Media::factory()->document()->create([
        'model_type' => $owner->getMorphClass(),
        'model_id' => $owner->getKey(),
    ]);

    Storage::disk('local')->put($media->path, 'secret');

    actingAs($owner)
        ->get(route('media.show', $media))
        ->assertForbidden();
});

test('a file with no owner is refused rather than served', function () {
    $user = User::factory()->create();

    $media = Media::factory()->document()->create([
        'model_type' => null,
        'model_id' => null,
    ]);

    Storage::disk('local')->put($media->path, 'secret');

    // Nothing to inherit authorisation from, so it must not default open.
    actingAs($user)
        ->get($media->signedUrl())
        ->assertForbidden();
});
