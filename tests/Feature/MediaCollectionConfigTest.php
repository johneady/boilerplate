<?php

use App\Media\MediaCollection;

/*
 * These live in Feature rather than Unit because they read config(), which
 * needs a booted container -- the enum itself is framework-free, and its
 * classification rules are pinned in tests/Unit/MediaCollectionTest.php.
 */

test('an image collection names a conversion set that actually exists', function () {
    // A set named here but absent from config makes the processing job throw
    // at run time, on the worker, after the upload has already been accepted.
    foreach (MediaCollection::cases() as $collection) {
        $set = $collection->conversionSet();

        if ($set === null) {
            continue;
        }

        expect(config("images.conversions.{$set}"))->toBeArray()->not->toBeEmpty();
    }
});

test('an image collection defaults to a conversion its set defines', function () {
    foreach (MediaCollection::cases() as $collection) {
        $set = $collection->conversionSet();

        if ($set === null) {
            expect($collection->defaultConversion())->toBeNull();

            continue;
        }

        expect(array_keys((array) config("images.conversions.{$set}")))
            ->toContain($collection->defaultConversion());
    }
});
