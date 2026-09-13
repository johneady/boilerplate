<?php

use App\Models\User;
use Pest\Browser\Api\PendingAwaitablePage;
use Pest\Browser\Api\Webpage;

/*
 * The admin panel is the densest JavaScript in the application -- Filament's
 * own bundle plus Livewire plus Alpine -- and the feature suite asserts only
 * that its pages return 200. A panel that boots with a JavaScript error still
 * returns 200 while being unusable, which is exactly what a dependency bump
 * tends to cause.
 */

/**
 * Browser notices that are not application faults.
 *
 * "ResizeObserver loop completed with undelivered notifications" means an
 * observer callback resized the element it was watching, so the browser
 * deferred the next delivery to the following frame. Nothing is broken and
 * nothing is lost -- it is the browser reporting that it did the right thing.
 * Filament's tabs do it while laying out the settings page.
 *
 * It is filtered rather than asserted against because it is environmental, not
 * behavioural: the same code raises it on one Chromium build and not the one
 * before it (a Playwright bump started it here), so a test that fails on it is
 * reporting the browser version rather than the application.
 *
 * Keep this list to notices proven benign. Anything reaching the assertion
 * below is still a failure, which is the point of these tests.
 *
 * @var list<string>
 */
const IGNORED_JAVASCRIPT_NOTICES = [
    'ResizeObserver loop completed with undelivered notifications',
    'ResizeObserver loop limit exceeded',
];

/**
 * Assert the page raised no JavaScript error other than the benign notices.
 *
 * assertNoJavaScriptErrors() is all-or-nothing, so the errors are read back out
 * of the page and filtered here. The message is built to name what was found,
 * because a bare "array is not empty" tells whoever hits it nothing.
 *
 * Untyped because visit() hands back a PendingAwaitablePage that proxies to the
 * Webpage through __call, so neither concrete type covers both call sites.
 */
function assertNoRealJavaScriptErrors(PendingAwaitablePage|Webpage $page): void
{
    /** @var array<int, array{message?: string}|string> $errors */
    $errors = $page->script('window.__pestBrowser?.jsErrors || []');

    if (! is_array($errors)) {
        $errors = [];
    }

    // Each entry is a bare message string in some plugin versions and a
    // ['message' => ...] array in others, so both shapes are read.
    $messages = array_map(
        fn (array|string $error): string => is_array($error)
            ? (string) ($error['message'] ?? '')
            : $error,
        $errors,
    );

    $unexpected = array_values(array_filter($messages, function (string $message): bool {
        foreach (IGNORED_JAVASCRIPT_NOTICES as $ignored) {
            if (str_contains($message, $ignored)) {
                return false;
            }
        }

        return $message !== '';
    }));

    expect($unexpected)->toBe([], 'Unexpected JavaScript errors: '.implode(' | ', $unexpected));
}

test('the admin dashboard renders without javascript errors', function () {
    $this->actingAs(User::factory()->admin()->create());

    $page = visit('/admin');

    $page->assertSee('Overview')
        ->assertNoJavaScriptErrors();
});

test('the settings page renders its tabs', function () {
    $this->actingAs(User::factory()->admin()->create());

    $page = visit('/admin/settings');

    $page->assertSee('Business')
        ->assertSee('Diagnostics');

    assertNoRealJavaScriptErrors($page);
});

/**
 * The Diagnostics tab's panel is rendered by Filament inside the tab, so this
 * is the only level at which "clicking the tab shows the report" is actually
 * proven -- the Livewire test can see the markup without it ever being
 * reachable by a click.
 */
test('the diagnostics tab shows its report when clicked', function () {
    $this->actingAs(User::factory()->admin()->create());

    $page = visit('/admin/settings');

    $page->click('Diagnostics')
        ->assertSee('Configuration audit');

    assertNoRealJavaScriptErrors($page);
});

test('the users list renders', function () {
    $this->actingAs(User::factory()->admin()->create(['name' => 'Ada Lovelace']));

    $page = visit('/admin/users');

    $page->assertSee('Ada Lovelace')
        ->assertNoJavaScriptErrors();
});
