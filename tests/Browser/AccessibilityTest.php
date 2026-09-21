<?php

use App\Models\User;
use App\Settings\SettingKey;
use App\Settings\Settings;

/*
 * axe-core over every page a person can reach, at every level except "minor".
 *
 * Nothing else in the suite can see these: a missing landmark, an unnamed
 * button or a 2.4:1 heading all render fine and return 200. The feature tests
 * prove the page exists; this proves it is usable without a mouse or a screen.
 *
 * Level 2 is critical + serious + moderate. Minor is left out on purpose: the
 * one minor finding on these pages (an actions column whose <th> has an
 * aria-label but no text) is Filament's markup, not ours, and failing the
 * build on a dependency's cosmetic rule is how a check like this gets
 * deleted.
 *
 * If a page is added to the application, add it here. If a finding is a
 * dependency's and cannot be fixed from this side, filter it in an
 * accessibility-specific helper rather than dropping the page or the level.
 *
 * /admin/pages is not listed yet: Filament's callout renders its heading as an
 * <h4> straight after the page's <h1> (heading-order, moderate), which is that
 * helper's first candidate.
 */

const ACCESSIBILITY_LEVEL = 2;

$publicPages = [
    'home' => '/',
    'contact' => '/contact',
    'login' => '/login',
    'register' => '/register',
    'forgot password' => '/forgot-password',
];

$userPages = [
    'dashboard' => '/dashboard',
    'profile settings' => '/settings/profile',
    'security settings' => '/settings/security',
    'appearance settings' => '/settings/appearance',
];

$adminPages = [
    'admin dashboard' => '/admin',
    'admin users' => '/admin/users',
    'admin settings' => '/admin/settings',
    'admin media' => '/admin/media',
    'admin contact submissions' => '/admin/contact-submissions',
    'admin audit logs' => '/admin/audit-logs',
    'admin account profile' => '/admin/account/profile',
    'admin account security' => '/admin/account/security',
];

test('the :dataset page has no accessibility issues', function (string $path) {
    // Registration is off by default and its routes 404 while it is, so
    // without this the register case would be checking the error page.
    app(Settings::class)->set(SettingKey::AllowRegistration, true);

    visit($path)->assertNoAccessibilityIssues(ACCESSIBILITY_LEVEL);
})->with($publicPages);

test('the :dataset page has no accessibility issues for a signed-in user', function (string $path) {
    $this->actingAs(User::factory()->create());

    visit($path)->assertNoAccessibilityIssues(ACCESSIBILITY_LEVEL);
})->with($userPages);

test('the :dataset page has no accessibility issues for an administrator', function (string $path) {
    // A named user so the users table has a real row -- and so the avatar
    // cell's row-click button has a name to be checked for.
    $this->actingAs(User::factory()->admin()->create(['name' => 'Ada Lovelace']));

    visit($path)->assertNoAccessibilityIssues(ACCESSIBILITY_LEVEL);
})->with($adminPages);
