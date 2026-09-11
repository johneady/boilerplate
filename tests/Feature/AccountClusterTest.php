<?php

use App\Filament\Clusters\Account\AccountCluster;
use App\Filament\Clusters\Account\Pages\Profile;
use App\Filament\Clusters\Account\Pages\Security;
use App\Livewire\Settings\Profile as SettingsProfile;
use App\Models\User;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

test('the user menu profile link points into the panel, not the flux settings page', function () {
    $profileItem = Filament\Facades\Filament::getPanel('admin')
        ->getUserMenuItems()['profile'];

    expect($profileItem->getUrl())
        ->toBe(Profile::getUrl())
        ->toContain('/admin/account/profile')
        ->not->toBe(route('profile.edit'));
});

test('the account cluster stays out of the sidebar', function () {
    expect(AccountCluster::shouldRegisterNavigation())->toBeFalse();
});

test('each account page renders inside the panel', function (string $page, string $path) {
    $this->withSession(['auth.password_confirmed_at' => time()])
        ->get($path)
        ->assertSuccessful();
})->with([
    'profile' => [Profile::class, '/admin/account/profile'],
    'security' => [Security::class, '/admin/account/security'],
]);

test('the cluster gives the account pages filament sub-navigation', function () {
    $html = $this->get('/admin/account/profile')->getContent();

    expect($html)->toContain('fi-page-sub-navigation');

    foreach ([Profile::getUrl(), Security::getUrl()] as $url) {
        expect($html)->toContain($url);
    }
});

test('the panel pages render the settings component without the flux chrome', function () {
    $html = $this->get('/admin/account/profile')->getContent();

    // The Flux settings navlist and heading belong to the /settings layout.
    // Rendering them here would put a second set of navigation and a second
    // "Settings" title inside a page that already has Filament's own.
    expect($html)
        ->not->toContain('Manage your profile and account settings')
        ->toContain('Change photo');
});

test('the flux settings page keeps its own chrome', function () {
    $html = $this->get(route('profile.edit'))->getContent();

    expect($html)
        ->toContain('Manage your profile and account settings')
        ->toContain('Change photo');
});

test('the bare flag cannot be flipped from the browser', function () {
    // Which chrome a settings component renders is the host page's decision.
    // Without #[Locked] a crafted update request could strip the Flux layout's
    // heading and settings navlist off /settings/profile.
    expect(fn () => Livewire::test(SettingsProfile::class)->set('bare', true))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});

test('the panel ships flux assets so the hosted components are not inert', function () {
    // The panel layout is not the Flux app layout, so it includes none of
    // Flux's runtime by default. Without it the avatar picker and the 2FA
    // modal render but do nothing.
    $html = $this->get('/admin/account/profile')->getContent();

    expect($html)->toContain('/flux/flux.js');
});

test('the panel theme compiles flux styles', function () {
    // Flux's stylesheet lives in app.css, which the panel never loads. Without
    // this import every Flux control inside the panel renders unstyled.
    $theme = file_get_contents(resource_path('css/filament/admin/theme.css'));

    expect($theme)->toContain('livewire/flux/dist/flux.css');
});

test('the panel security page is gated behind password confirmation', function () {
    $this->get('/admin/account/security')
        ->assertRedirect(route('password.confirm'));
});

test('non-admins may not reach the account pages', function () {
    $this->actingAs(User::factory()->create())
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get('/admin/account/profile')
        ->assertForbidden();
});

test('guests are sent to the login page', function () {
    auth()->logout();

    $this->get('/admin/account/profile')->assertRedirect(route('login'));
});

test('the panel leaves dark mode to filament', function () {
    // @fluxAppearance is a second appearance system keyed on flux.appearance,
    // and it runs after Filament's. With no Flux key set it resolves to
    // 'system' and strips html.dark, silently undoing a Dark choice made in
    // Filament's own theme switcher on the next page load.
    $html = $this->get('/admin/account/profile')->getContent();

    expect($html)
        ->not->toContain('flux.appearance')
        ->toContain('fi-theme-switcher');
});

test('the panel renders a toast group so saves confirm', function () {
    // The hosted components report success through Flux::toast(). Flux drops a
    // toast when the page renders no group to put it in, so without this a
    // changed password would confirm nothing.
    $html = $this->get('/admin/account/profile')->getContent();

    expect($html)->toContain('toast');
});
