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
 *
 * Only a STALE file fails. While `npm run dev` is running (so `composer run
 * dev` and the suite side by side) the file is correct, and the dev server it
 * names answers; the test is skipped then rather than failing every local run.
 */

test('no stale vite hot file is present', function () {
    $hotFile = Vite::hotFile();

    if (file_exists($hotFile) && viteDevServerIsListening((string) file_get_contents($hotFile))) {
        $this->markTestSkipped('The Vite dev server named in public/hot is running, so the file is not stale.');
    }

    expect(file_exists($hotFile))->toBeFalse(
        "A stale Vite hot file exists at [{$hotFile}]: nothing is listening at the dev server it names.\n\n".
        'Laravel will point every @vite asset URL at that dev server, so the '.
        "app's CSS and JS will 404 until the file is gone.\n\n".
        "Delete it:\n".
        '    rm public/hot'
    );
});

/**
 * Whether anything accepts a connection at the URL a hot file names.
 */
function viteDevServerIsListening(string $url): bool
{
    $parts = parse_url(trim($url));

    if (! isset($parts['host'], $parts['port'])) {
        return false;
    }

    $socket = @stream_socket_client("tcp://{$parts['host']}:{$parts['port']}", $errorCode, $errorMessage, 0.5);

    if ($socket === false) {
        return false;
    }

    fclose($socket);

    return true;
}
