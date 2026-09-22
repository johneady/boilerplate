---
paths:
  - 'tests/Browser/**'
  - tests/Pest.php
---

# Browser

## Pest browser tests: no file uploads, no WebAuthn, and Playwright must match the cached browser
tests/Browser is bound to TestCase + RefreshDatabase in tests/Pest.php and declared as its own <testsuite> in phpunit.xml. Without both, browser tests fail with "Target class [config] does not exist".

File uploads do NOT work. The plugin's in-process server builds its Symfony request with `[], // @TODO files...` (Drivers/LaravelHttpServer.php), so every uploaded file is dropped and Livewire then throws "Undefined array key 0" in WithFileUploads::_finishUpload(). Verified the same avatar upload succeeds in real Chrome against `php artisan serve` -- so a failure here is the harness, not the app. Do not add browser coverage for uploads until the plugin implements it.

WebAuthn/passkeys cannot be driven either: the plugin exposes no CDP session, so there is no way to install a virtual authenticator. tests/Feature/Auth/PasskeyLoginOptionsTest.php covers the server half (the challenge payload shape and rpId) instead.

Playwright's npm version must match a browser build in ~/.cache/ms-playwright, since the CDN download can be blocked. playwright 1.62.0 -> chromium-1234, 1.61.0 -> chromium-1228, 1.63.0 -> chromium-1243. Pinned to 1.62.0 in package.json for that reason.

The pin is EXACT (`"playwright": "1.62.0"`, no caret) and must stay that way. A caret range does not express this constraint: `^1.62.0` resolves to the newest 1.x, so a routine `npm install` silently pulls a Playwright whose required chromium is not cached. That is how 1.63.0 got in (commit c3cba3d bumped package.json and package-lock.json without an `npm install`, leaving node_modules on 1.62.0 and the tree inconsistent). Installing the locked 1.63.0 then wants chromium-1243, and the download times out here:

    Error: Request to https://cdn.playwright.dev/builds/cft/153.0.8010.12/linux64/chrome-linux64.zip timed out after 30000ms

Symptom of a mismatch: `vendor/bin/pest tests/Browser` spins at ~100% CPU with no `playwright run-server` process and produces no output. To move the pin, first confirm the new version's chromium revision is in ~/.cache/ms-playwright (node_modules/playwright-core/browsers.json names it), then update package.json, run `npm install` so the lockfile and node_modules agree, and update this note.

Note that pest-plugin-browser v5.0.1 requires playwright >= 1.62.1 in PlaywrightNpmServer::PLAYWRIGHT_VERSION, but only checks it on a FAILED start, so 1.62.0 runs fine. Bumping the plugin may force this pin forward and require fetching a new chromium build.

Assert paths with assertPathIs(), never assertUrlIs() -- the test server binds an ephemeral port.

## pest-plugin-browser v5.0.1 leaks its playwright server; tests/Pest.php kills it
Every browser run under pest-plugin-browser v5.0.1 orphans its `playwright run-server` process, and they ACCUMULATE (verified: one run leaves one, a second leaves two; 84 had built up holding 10.7GB). Cause: PlaywrightNpmServer::stop() calls Symfony Process::stop(timeout: 0.1) — SIGTERM plus a 100ms grace — and run-server does not exit in that window, so it survives and is reparented to init. The sibling AlreadyStartedPlaywrightServer::stop() (the parallel path) has an EMPTY body.

This is also why a browser run appears to hang with no output: Pest exits with tests passed, but the shell waits on a pipe the orphan still holds. An empty output file means "blocked on exit", NOT "still running" — do not read it as a slow test. Wall clock is ~5s for the 7 browser tests.

tests/Pest.php registers a shutdown function that kills them. It matches on the process WORKING DIRECTORY (/proc/<pid>/cwd == project root), not port or name: the plugin deletes its state file (markAsStopped) BEFORE shutdown functions run — verified, the hook fires 3x per run and finds no file every time — so the port is unknowable by then, and a bare `pkill -f playwright` would reach into another checkout. Guarded to Linux + ext-posix so it is inert rather than fatal elsewhere.

Delete the hook once the plugin tears its own server down. Check this when bumping pest-plugin-browser.

## The teardown hook MUST be skipped in parallel workers
The shutdown hook in tests/Pest.php returns early when `Pest\Plugins\Parallel::isWorker()`. Do not remove that guard: without it `--parallel` browser runs fail 7/7 with `Connection to 127.0.0.1:<port> failed`, or a PHP fatal "Possible integer overflow in memory allocation" from iterator_to_array() in InteractsWithPlaywright::processVoidResponse() as the WebSocket client reads a dead stream.

Under --parallel there is only ONE run-server: ServerManager::playwright() starts it in the parent and persists host/port, and each worker (`Parallel::isWorker()`, i.e. PARATEST=1) reconnects via AlreadyStartedPlaywrightServer. That class's empty stop() is therefore CORRECT and deliberate — the shared server is not a worker's to stop — not the oversight it might look like. A worker exits as soon as its queue drains, so an unguarded hook kills the shared server while sibling workers are still driving it.

Only the parent may reap the leak, and it runs the hook after all workers have finished. Verified both ways: guard removed -> 0/7 pass; guard present -> 7/7 pass and 0 orphans, in both --parallel and serial runs.

## Media test helpers live on TestCase
`$this->giveAvatar($user)` and `$this->storeLogo()` create the media row AND write its conversion files to the faked disk — both are needed, since Media::url() returns null for a row whose files are absent (that is the in-flight state, not a stored image). `giveAvatar($user, conversions: ['full'])` writes a subset, for the missing-conversion fallback.

MediaFactory: `->logo()` sets conversions, so `->pending()` must come AFTER it (`Media::factory()->logo()->pending()`) or the logo state puts them back.

UploadedFile::fake()->createWithContent() with an image extension makes Laravel try to decode it, throwing ImageDecoderException before validation runs. To test that a bad upload is REJECTED, offer a real file of the wrong type (a .pdf to an image collection) rather than a fake payload named .jpg.
