<?php

namespace App\Media;

/**
 * The rule for which staged upload paths may be adopted.
 *
 * Lives here rather than on a Filament page because it guards the MEDIA layer:
 * App\Media\MediaManager enforces it on every adoption, and a rule owned by one
 * screen would make the application layer depend on the panel -- backwards, and
 * it breaks the moment a console command or a second panel needs the same
 * check.
 */
final class StagedUpload
{
    /**
     * The directory a component may stage an upload into.
     */
    public const string DIRECTORY = 'uploads/pending';

    /**
     * Whether a dehydrated upload path may be handed to the processing job.
     *
     * Exactly "uploads/pending/<name>": a prefix match alone would admit
     * "uploads/pending/../../elsewhere", since the filesystem resolves the dot
     * segments after the prefix is checked. One bare filename -- no further
     * separators, no dot-prefixed segment -- is also exactly what Filament
     * stores there, a hashed name directly inside the directory.
     *
     * This matters because dehydrated FileUpload state is client-controllable:
     * a forged request can submit an arbitrary path string instead of a fresh
     * upload (see BaseFileUpload::saveUploadedFiles). Without this, nothing
     * stops one naming any file the application can read.
     *
     * A pure predicate rather than an inline check so the rule is pinned by a
     * test of its own, independent of whichever layer rejects a forged string
     * first.
     */
    public static function isStagedPath(string $path): bool
    {
        // Control characters are refused before the pattern runs. `$` matches
        // before a trailing newline in PCRE, and a path containing one reaches
        // Flysystem as a CorruptedPathDetected exception -- an unhandled 500
        // where a clean refusal belongs. No legitimate staged name has one.
        if (preg_match('/[\x00-\x1F\x7F]/', $path) === 1) {
            return false;
        }

        return (bool) preg_match('#^'.preg_quote(self::DIRECTORY, '#').'/[^/.\\\\][^/\\\\]*$#', $path);
    }
}
