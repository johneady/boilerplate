<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
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
    ->in('Feature');

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

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}
