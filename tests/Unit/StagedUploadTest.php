<?php

use App\Media\StagedUpload;

/*
 * The rule guarding which paths may be adopted from the staging directory.
 * Dehydrated FileUpload state is client-controllable, so this is what stops a
 * forged request naming any file the application can read.
 *
 * A unit test deliberately: the predicate is pure, and it must hold wherever it
 * is reached rather than only where a form happens to reject a shape first.
 */
test('it accepts a bare staged filename', function () {
    expect(StagedUpload::isStagedPath('uploads/pending/abc123'))->toBeTrue()
        ->and(StagedUpload::isStagedPath('uploads/pending/a-b_c.png'))->toBeTrue();
});

test('it refuses traversal out of the staging directory', function (string $path) {
    // A prefix match alone would admit these: the filesystem resolves the dot
    // segments only after the prefix has been checked.
    expect(StagedUpload::isStagedPath($path))->toBeFalse();
})->with([
    'uploads/pending/../../.env',
    'uploads/pending/../secrets.txt',
    'uploads/pending/.hidden',
    'uploads/pending/nested/file.png',
    'uploads/pending/',
    'uploads/pending',
    '../../.env',
    'secrets.txt',
    'other/pending/file.png',
    'uploads/pending/sub\\file',
]);

test('it refuses a path carrying a control character', function (string $path) {
    // PCRE's `$` matches before a trailing newline, so the pattern alone would
    // accept these -- and Flysystem then throws CorruptedPathDetected, an
    // unhandled 500 where a clean refusal belongs.
    expect(StagedUpload::isStagedPath($path))->toBeFalse();
})->with([
    'trailing newline' => "uploads/pending/file\n",
    'embedded newline' => "uploads/pending/a\nb",
    'null byte' => "uploads/pending/a\0b",
    'carriage return' => "uploads/pending/a\rb",
    'tab' => "uploads/pending/a\tb",
]);

test('the staging directory is one place, not a literal repeated per call site', function () {
    expect(StagedUpload::DIRECTORY)->toBe('uploads/pending')
        ->and(StagedUpload::isStagedPath(StagedUpload::DIRECTORY.'/file'))->toBeTrue();
});
