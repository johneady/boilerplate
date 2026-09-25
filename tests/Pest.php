<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Vite;
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
| Browser tests use the built assets, never the Vite dev server
|--------------------------------------------------------------------------
|
| While `npm run dev` is running, public/hot points @vite at the dev server,
| and every page a browser test opens would load @vite/client and hold a live
| HMR connection to it. Vite then reloads that page whenever a watched file
| changes -- a save in resources/views, app/Livewire, lang or routes, by an
| editor or an agent, while the suite runs -- and a reload during an
| assertion fails it with "Execution context was destroyed, most likely
| because of a navigation". Verified: touching a view mid-test reloads the
| page under test.
|
| So the hot file is pointed somewhere that never exists, and the pages load
| public/build exactly as they do in CI. That needs a build: run `npm run
| build` after changing CSS or JS, or the browser tests check the old assets.
| Only the Browser directory: feature tests render @vite without loading
| anything, and should not start requiring a build.
|
*/

pest()->in('Browser')->beforeEach(function (): void {
    Vite::useHotFile(storage_path('framework/testing/vite-dev-server-not-used'));
});

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
| One test run at a time per checkout
|--------------------------------------------------------------------------
|
| Two Pest runs in this checkout at once break each other's browser tests.
| pest-plugin-browser v5.0.1 keeps its Playwright server's address in ONE file
| (vendor/pestphp/pest-plugin-browser/.temp/playwright-server.json), which
| every run rewrites and deletes when it finishes -- even a run with no browser
| tests in it -- and the teardown hook below kills every server started from
| this checkout. So a second run finishing mid-way through a first takes the
| first's server away, and the first's browser workers then busy-loop forever
| at 100% CPU: Playwright\Client::execute() reads a closed WebSocket as an
| empty message and loops again, with no timeout. That is what made a full
| `composer test` appear to hang while another run (an editor, an agent, a
| second terminal) came and went.
|
| So the run takes an exclusive lock for the checkout and holds it until it
| exits; a second run waits for the first, saying so. Only the parent process
| takes it: parallel workers belong to the run that already holds it. It is
| taken here, while tests are loading -- before the plugin starts a server
| for any browser test in the run, and long before its end-of-run cleanup.
|
*/

/*
| A run can also re-execute itself: with TIA on, Pest's PcovRestarter starts a
| child PHP process (to set pcov.directory) after this file has already taken
| the lock in the parent, then waits for that child. The child is not a
| parallel worker, so without this it would wait on its own parent forever.
| The holder names itself in the environment, which the child inherits, and a
| process descended from the holder runs under the lock its ancestor holds.
*/

$pestRunLockHolder = (int) getenv('PEST_RUN_LOCK_HOLDER');

$pestRunIsUnderHeldLock = (static function (int $holder): bool {
    if ($holder <= 0) {
        return false;
    }

    // Walk up the process tree: the holder must be an ancestor, not merely
    // named in an environment copied into an unrelated shell.
    for ($pid = getmypid(), $depth = 0; $pid > 1 && $depth < 16; $depth++) {
        if ($pid === $holder) {
            return true;
        }

        $stat = @file_get_contents("/proc/{$pid}/stat");

        if ($stat === false) {
            // No /proc (not Linux): trust the inherited name.
            return true;
        }

        // The ppid is the second field after the ")" closing the command name.
        $pid = (int) (explode(' ', substr($stat, strrpos($stat, ')') + 2))[1] ?? 0);
    }

    return false;
})($pestRunLockHolder);

if (! Parallel::isWorker() && ! $pestRunIsUnderHeldLock) {
    $pestRunLock = fopen(sys_get_temp_dir().DIRECTORY_SEPARATOR.'pest-'.md5(dirname(__DIR__)).'.lock', 'c+');

    if ($pestRunLock !== false && ! flock($pestRunLock, LOCK_EX | LOCK_NB)) {
        // The holder writes its pid and command line into the file (below),
        // so the wait names the run it is waiting for instead of leaving the
        // developer to go looking for it.
        $holder = trim((string) stream_get_contents($pestRunLock, offset: 0));

        fwrite(STDERR, 'Another test run is using this checkout; waiting for it to finish...'
            .($holder !== '' ? "\n  Held by: {$holder}\n  (stop it with: kill ".strtok($holder, ' ').')' : '')
            ."\n");
        flock($pestRunLock, LOCK_EX);
    }

    if ($pestRunLock !== false) {
        $command = is_readable('/proc/self/cmdline')
            ? trim(str_replace("\0", ' ', (string) file_get_contents('/proc/self/cmdline')))
            : implode(' ', $_SERVER['argv'] ?? []);

        ftruncate($pestRunLock, 0);
        fwrite($pestRunLock, getmypid().' '.$command);
        fflush($pestRunLock);

        putenv('PEST_RUN_LOCK_HOLDER='.getmypid());
    }

    // Held for the life of the process: the lock is released when the handle
    // is, which PHP does only at exit while this reference is alive.
    $GLOBALS['pestRunLock'] = $pestRunLock;
}

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
