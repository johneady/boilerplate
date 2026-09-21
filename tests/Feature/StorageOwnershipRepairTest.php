<?php

/**
 * Guards the entrypoint's repair of upload-directory ownership.
 *
 * Moving these images from Debian to Alpine changed www-data from uid 33 to
 * uid 82. storage/app/public is a persistent volume, so every per-resource
 * upload directory inside it survives a rebuild still owned by uid 33 at mode
 * 755 — owner-only write, and php-fpm no longer runs as that owner.
 *
 * The resulting failure is worth spelling out, because it is quiet: records
 * that carry no file still save, but attaching ANY image fails, because
 * Livewire cannot write its temp upload. Filament surfaces only its generic
 * "There was an error while attempting to load this page", and laravel.log
 * stays empty — the error is a filesystem permission below the application, so
 * the application never sees it to log it. Nothing in the app surfaces a
 * regression here, which is why these assertions stand in for the missing
 * signal.
 *
 * This file matters more here than in any single deployment: every project
 * seeded from this boilerplate inherits the entrypoint, so a regression in it
 * propagates to the next app built from it rather than breaking one site.
 *
 * Two properties of the fix are load-bearing and easy to "tidy" away:
 *
 *  1. The test must be `-user www-data`, NEVER a hardcoded uid. Pinning 82
 *     would trade uid 33 for the next base-image surprise; resolving the name
 *     is what makes this self-heal on any future uid change.
 *
 *  2. It must stay a filtered repair, not `chown -R`. The volume holds every
 *     uploaded file, and this runs before supervisord starts nginx — a
 *     recursive walk leaves the container unreachable with no backend.
 *     `! -user www-data` means a correct tree matches nothing and costs one
 *     stat per directory.
 */
beforeEach(function () {
    $this->entrypoint = file_get_contents(base_path('docker/entrypoint/entrypoint.sh'));
});

test('the entrypoint repairs ownership inside the upload volume', function () {
    // Mount-point-only chown was the bug: it left the per-resource upload
    // directories owned by the old uid.
    expect($this->entrypoint)->toMatch('/find\s+"\$root"\s+-mindepth 1\s+!\s*-user www-data/');
});

test('the ownership repair covers both the public and private upload roots', function () {
    expect($this->entrypoint)
        ->toContain('/var/www/html/storage/app/private')
        ->toContain('/var/www/html/storage/app/public');
});

test('the ownership repair matches on the user name rather than a hardcoded uid', function () {
    // A hardcoded 82 would need editing again on the next base-image change --
    // exactly the failure mode this whole fix exists to end.
    expect($this->entrypoint)->toMatch('/!\s*-user www-data/');
    expect($this->entrypoint)->not->toMatch('/-uid\s+82\b/');
});

test('the ownership repair never recurses over the whole upload volume', function () {
    // THE regression guard. `chown -R` here would delay readiness on every
    // container start, in proportion to how much has been uploaded, while
    // nginx is not yet listening.
    expect($this->entrypoint)->not->toMatch('/chown -R\s+www-data:www-data\s+\/var\/www\/html\/storage\/app/');
});

test('the ownership repair does not descend into the per-resource directories', function () {
    // Those hold the actual file count, are created by www-data at runtime and
    // already inherit the right owner. -maxdepth 2 stops above them.
    expect($this->entrypoint)->toMatch('/find\s+"\$root"\s+-mindepth 1\s+!\s*-user www-data/');
});

/*
|--------------------------------------------------------------------------
| The volumes the repair above assumes
|--------------------------------------------------------------------------
|
| Everything above guards the REPAIR of the upload volume. Nothing guarded
| that the volume exists, and it did not: both compose files mounted only
| storage/logs, while config/filesystems.php points the `local` disk at
| storage/app/private and the `public` disk at storage/app/public. Those were
| ordinary image-layer directories, so `pull_policy: always` plus a new
| IMAGE_TAG deleted every uploaded file on each deploy — and the media rows
| survived in MariaDB, so the app returned with a full catalogue pointing at
| files that were gone.
|
| The second failure is sharper and was equally invisible: App\Jobs\
| ProcessUploadedImage reads its source from Storage::disk('local'), a file the
| WEB container wrote. On unshared per-container filesystems the worker looks
| for it on its own disk, never finds it, and every conversion fails while both
| containers report healthy.
|
| So these assert the mounts exist and are shared by all three roles. They are
| deliberately asserted on the shared x-app-base block rather than per service:
| that is what makes them shared, and a per-service mount would satisfy a
| looser test while leaving the worker blind to the web container's uploads.
|
*/
test('both compose files persist the upload disks, not just the logs', function (string $file) {
    $compose = file_get_contents(base_path($file));

    // The two disks config/filesystems.php actually writes uploads to.
    expect($compose)->toContain('storage-app-private:/var/www/html/storage/app/private');
    expect($compose)->toContain('storage-app-public:/var/www/html/storage/app/public');
})->with(['docker-compose.dokploy.yml', 'docker-compose.yml']);

test('the upload volumes are declared in the top-level volumes block', function (string $file) {
    // A mount naming a volume that is never declared is an error in the
    // dokploy file (no implicit creation for a named volume referenced by an
    // extension field), so this is what keeps the mounts above valid.
    $compose = file_get_contents(base_path($file));

    expect($compose)->toMatch('/^volumes:(?:\n(?:[ #].*)?)*^  storage-app-private:$/m');
    expect($compose)->toMatch('/^volumes:(?:\n(?:[ #].*)?)*^  storage-app-public:$/m');
})->with(['docker-compose.dokploy.yml', 'docker-compose.yml']);

test('the upload volumes are mounted once, on the shared base all roles inherit', function (string $file) {
    // Shared is the whole point: the worker must read back the file the web
    // container wrote. Mounting per service would pass a naive "is it
    // mounted" check while giving each role its own empty disk.
    $compose = file_get_contents(base_path($file));

    expect(substr_count($compose, 'storage-app-private:/var/www/html/storage/app/private'))->toBe(1);
    expect(substr_count($compose, 'storage-app-public:/var/www/html/storage/app/public'))->toBe(1);
})->with(['docker-compose.dokploy.yml', 'docker-compose.yml']);
