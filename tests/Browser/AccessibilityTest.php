<?php

use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Resources\Plans\PlanResource;
use App\Filament\Resources\Subscriptions\SubscriptionResource;
use App\Models\PaymentLink;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\User;
use App\Settings\SettingKey;
use App\Settings\Settings;
use Tests\Support\Payments;

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
 *
 * The payments pages need the module switched on and records to show, so
 * each case is a closure that arranges its own and returns the path. The
 * parameter is typed Closure so Pest hands it over unresolved, to be called
 * after Payments::enable() rather than before it.
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

$paymentPages = [
    'pricing' => function (): string {
        PlanPrice::factory()->for(Plan::factory()->trial()->state(['name' => 'Pro']))->create();

        return '/pricing';
    },
    'pay' => fn (): string => route('payments.pay', PaymentLink::factory()->create(['title' => 'Logo design']), absolute: false),
    'receipt' => fn (): string => Payments::payWithDemo(PaymentLink::factory()->create())->receiptUrl(),
];

$adminPaymentPages = [
    'admin payments' => fn (): string => '/admin/payments',
    'admin payment' => fn (): string => PaymentResource::getUrl('view', [
        'record' => Payments::payWithDemo(PaymentLink::factory()->create()),
    ], isAbsolute: false),
    'admin payment links' => fn (): string => '/admin/payment-links',
    'admin tax rates' => fn (): string => '/admin/tax-rates',
    'admin webhook events' => fn (): string => '/admin/webhook-events',
    'admin plans' => fn (): string => '/admin/plans',
    'admin plan' => fn (): string => PlanResource::getUrl('edit', [
        'record' => PlanPrice::factory()->create()->plan,
    ], isAbsolute: false),
    'admin subscriptions' => fn (): string => '/admin/subscriptions',
    'admin subscription' => fn (): string => SubscriptionResource::getUrl('view', [
        'record' => Payments::subscribeWithDemo(User::factory()->create(), PlanPrice::factory()->create()),
    ], isAbsolute: false),
    'admin disputes' => fn (): string => '/admin/disputes',
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

test('the :dataset payments page has no accessibility issues', function (Closure $arrange) {
    Payments::enable();

    visit($arrange())->assertNoAccessibilityIssues(ACCESSIBILITY_LEVEL);
})->with($paymentPages);

test('the billing page has no accessibility issues for a subscriber', function () {
    Payments::enable();
    $user = User::factory()->create();
    Payments::subscribeWithDemo($user, PlanPrice::factory()->create());

    $this->actingAs($user);

    visit('/settings/billing')->assertNoAccessibilityIssues(ACCESSIBILITY_LEVEL);
});

test('the :dataset screen has no accessibility issues for an administrator', function (Closure $arrange) {
    Payments::enable();
    $this->actingAs(User::factory()->admin()->create(['name' => 'Ada Lovelace']));

    visit($arrange())->assertNoAccessibilityIssues(ACCESSIBILITY_LEVEL);
})->with($adminPaymentPages);
