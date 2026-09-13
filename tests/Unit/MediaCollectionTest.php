<?php

use App\Media\MediaCollection;

test('every collection classifies itself in every match', function (MediaCollection $collection) {
    // Each of these is a `match` with no default arm, so a case added without
    // being classified raises UnhandledMatchError rather than silently
    // inheriting avatar behaviour.
    expect(fn () => $collection->conversionSet())->not->toThrow(Throwable::class)
        ->and(fn () => $collection->isSingle())->not->toThrow(Throwable::class)
        ->and(fn () => $collection->defaultConversion())->not->toThrow(Throwable::class)
        ->and(fn () => $collection->directory())->not->toThrow(Throwable::class);
})->with(MediaCollection::cases());

test('a collection with no conversion set is private', function () {
    // The rule the whole pipeline turns on: nothing re-encodes a document, so
    // it must never be served from the public origin.
    foreach (MediaCollection::cases() as $collection) {
        expect($collection->isPublic())->toBe($collection->conversionSet() !== null);
    }
});

test('a private collection is stored off the public disk', function () {
    foreach (MediaCollection::cases() as $collection) {
        if ($collection->isPublic()) {
            continue;
        }

        expect($collection->disk())->toBe('local');
    }
});

test('every collection has its own directory', function () {
    $directories = array_map(
        fn (MediaCollection $c): string => $c->directory(),
        MediaCollection::cases(),
    );

    // Two collections sharing a directory would let replacing one delete the
    // other's files.
    expect($directories)->toBe(array_unique($directories));
});
