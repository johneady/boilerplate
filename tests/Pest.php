<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Pest\Plugins\Parallel;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Browser', 'Sandbox');

/*
|--------------------------------------------------------------------------
| TIA Engine (Test Impact Analysis)
|--------------------------------------------------------------------------
|
| The Tia Engine records which tests touch which files, then re-runs only the
| tests affected by your latest changes, replaying cached results for the rest.
| `locally()` activates it on every local `pest`/`artisan test` run without the
| `--tia` flag. It requires the PCOV coverage driver.
|
| Note that "locally" is not automatic: Pest's environment defaults to LOCAL
| and only becomes CI when `--ci` is passed explicitly. Pass that flag in CI so
| the pipeline runs the full suite rather than trusting a cached dependency
| graph. Because `artisan test` does not forward `--ci`, CI should invoke
| `vendor/bin/pest` directly.
|
| `defaultBranch('main')` names the baseline branch explicitly so a fresh
| checkout (which may lack an origin/HEAD) doesn't have to run
| `git remote set-head origin --auto` before Tia will activate.
|
*/

pest()->tia()->defaultBranch('main')->locally();

/*
|--------------------------------------------------------------------------
| Playwright server teardown
|--------------------------------------------------------------------------
|
| Works around a leak in pest-plugin-browser v5.0.1: every browser run orphans
| its `playwright run-server` process, and they accumulate (verified: one run
| leaves one behind, a second leaves two). Each holds ~130MB, so a day of local
| runs had built up 84 servers holding 10.7GB here.
|
| The cause is in Playwright\Servers\PlaywrightNpmServer::stop(), which calls
| Symfony's Process::stop(timeout: 0.1). That sends SIGTERM and waits 100ms for
| SIGKILL -- but `playwright run-server` does not exit in that window, so it
| survives its parent. Pest then exits with the tests passed while the server
| keeps its port open, which is also why a browser run appears to hang: the
| shell waits on a pipe the orphan still holds. (Its sibling class,
| AlreadyStartedPlaywrightServer::stop(), is an empty method body -- so the
| parallel path never even tries.)
|
| This kills only servers whose working directory is THIS checkout, so a
| concurrent run in another project -- or anyone else's playwright on the
| machine -- is left alone. It is a workaround, not a fix: delete it once the
| plugin tears its own server down, and see .ai/rules/browser.md before bumping
| the plugin or playwright itself.
|
*/

register_shutdown_function(function (): void {
    // Parallel workers must never reach the kill below. Under --parallel the
    // plugin starts ONE shared run-server in the parent and every worker
    // connects to it via AlreadyStartedPlaywrightServer (whose stop() is empty
    // precisely because the server is not the worker's to stop -- see
    // ServerManager::playwright()). A worker exits as soon as its queue drains,
    // which fires this hook while siblings are still driving that same server:
    // killing it here tears the WebSocket out from under them, surfacing as
    // "Connection to 127.0.0.1:<port> failed" or a fatal integer overflow in
    // iterator_to_array() as the client reads a dead stream.
    //
    // Pest\Plugins\Parallel::isWorker() is the plugin's own discriminator
    // (PARATEST=1 in the environment), so this defers to the same signal the
    // server-selection logic uses. The parent runs this hook after every worker
    // has finished, which is the only safe moment to reap the leak.
    if (Parallel::isWorker()) {
        return;
    }

    // Linux only, and deliberately so: the identification below reads /proc,
    // and a workaround for a vendor bug must never be the reason a test run
    // fails somewhere it would otherwise have worked. Elsewhere the leak stays,
    // which is the status quo rather than a regression.
    //
    // SIGKILL is checked separately from posix_kill() because they come from
    // DIFFERENT extensions: posix_kill() is ext-posix, but the SIGKILL constant
    // is defined by ext-pcntl. posix is commonly bundled while pcntl is opt-in
    // (this project's own Dockerfile installs neither), so testing only for the
    // function would let a box with posix and no pcntl past this guard and then
    // fatal on an undefined constant -- inside a shutdown function, which is
    // exactly the failure this guard exists to prevent.
    if (PHP_OS_FAMILY !== 'Linux' || ! function_exists('posix_kill') || ! defined('SIGKILL')) {
        return;
    }

    // Servers are matched by their WORKING DIRECTORY, not by port or process
    // name. The port would be the precise signal, but the plugin deletes its
    // own state file (AlreadyStartedPlaywrightServer::markAsStopped) before
    // shutdown functions run -- verified: the hook fires three times per run
    // and finds no file every time -- so by here the port is unknowable.
    // A bare `pkill -f playwright` would reach into another checkout; the cwd
    // is what makes a server ours.
    $project = dirname(__DIR__);

    $candidates = [];
    exec('ps -eo pid,args --no-headers 2>/dev/null', $candidates, $status);

    if ($status !== 0) {
        return;
    }

    foreach ($candidates as $line) {
        if (! preg_match('/^\s*(\d+)\s+(\S+)\s+(.*)$/', $line, $matches)) {
            continue;
        }

        [, $pid, $binary, $arguments] = $matches;

        // The plugin spawns exactly `node ./node_modules/.bin/playwright
        // run-server --host ... --mode launchServer`. Both markers are required
        // so an ordinary `playwright test` or a developer's own node process is
        // never a candidate.
        if (basename($binary) !== 'node'
            || ! str_contains($arguments, 'playwright')
            || ! str_contains($arguments, 'run-server')) {
            continue;
        }

        if (@readlink('/proc/'.$pid.'/cwd') !== $project) {
            continue;
        }

        // SIGKILL rather than SIGTERM: the plugin already tried SIGTERM and a
        // 100ms grace, and this process is what ignored it.
        posix_kill((int) $pid, SIGKILL);
    }
});

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});
