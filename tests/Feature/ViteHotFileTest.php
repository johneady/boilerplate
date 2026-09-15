<?php

use Illuminate\Support\Facades\Vite;

/*
 * A stale public/hot silently breaks every page that renders @vite.
 *
 * `npm run dev` writes the file and removes it on a clean exit; a killed
 * terminal, a crash or a closed laptop leaves it behind. Laravel then treats
 * the app as running hot and points every asset URL at the dev server that is
 * no longer listening, so CSS and JS 404 and nothing on the page works --
 * while the HTML still renders and the feature suite still passes, because it
 * asserts server-side output and never loads an asset.
 *
 * This was not hypothetical: a sibling project carried one from an abandoned
 * August session, and it presented as three "undefined" JavaScript errors on
 * the dashboard that looked exactly like an application bug.
 *
 * The container is already protected -- the Dockerfile deletes the file at
 * build time and the entrypoint again at boot -- so this covers the case
 * neither can: a developer's own checkout, and CI running against it.
 */

test('no stale vite hot file is present', function () {
    $hotFile = Vite::hotFile();

    expect(file_exists($hotFile))->toBeFalse(
        "A Vite hot file exists at [{$hotFile}].\n\n".
        'Laravel will point every @vite asset URL at the dev server named in '.
        "that file, so the app's CSS and JS will 404 for anyone who is not ".
        "running `npm run dev` right now.\n\n".
        "If the dev server is not running, delete it:\n".
        "    rm public/hot\n\n".
        'If it IS running, stop it before running the suite -- an asset URL '.
        'pointing at localhost only works on your own machine.'
    );
});
