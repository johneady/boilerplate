<?php

use App\Models\User;
use App\Settings\SettingKey;
use App\Settings\Settings;
use Filament\Auth\Http\Responses\Contracts\LogoutResponse;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Support\Facades\Storage;

test('the panel does not register a login page of its own', function () {
    expect(Route::has('filament.admin.auth.login'))->toBeFalse();
});

test('guests are redirected to the application login page', function () {
    $this->get('/admin')->assertRedirect(route('login'));
});

test('non-admins are forbidden from the admin panel', function () {
    $this->actingAs(User::factory()->create())
        ->get('/admin')
        ->assertForbidden();
});

test('admins may access the admin panel', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin')
        ->assertSuccessful();
});

test('is_admin cannot be mass assigned', function () {
    // The guard is User::$fillable. Outside production Model::shouldBeStrict()
    // turns the silent discard into an exception, which is what is asserted
    // here; in production the same unfillable key is dropped and the row is
    // created without it. Either way a forged payload cannot grant itself the
    // flag, and the second assertion proves the attempt persisted nothing.
    expect(fn () => User::create([
        'name' => 'Mass Assigned',
        'email' => 'mass@example.com',
        'password' => 'password',
        'is_admin' => true,
    ]))->toThrow(MassAssignmentException::class);

    expect(User::query()->where('email', 'mass@example.com')->exists())->toBeFalse();
});

test('logging out of the panel returns to the home page', function () {
    $response = app(LogoutResponse::class)
        ->toResponse(request());

    expect($response->getTargetUrl())->toBe(url('/'));
});

test('admins are redirected to the admin panel after logging in', function () {
    $user = User::factory()->admin()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(Filament\Facades\Filament::getPanel('admin')->getUrl());
});

test('admins reach the panel even after being bounced off the dashboard', function () {
    $user = User::factory()->admin()->create();

    $this->get('/dashboard')->assertRedirect(route('login'));

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(Filament\Facades\Filament::getPanel('admin')->getUrl());
});

test('admins still land on a deliberate deep link captured before login', function () {
    $user = User::factory()->admin()->create();

    $this->get(route('profile.edit'))->assertRedirect(route('login'));

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('profile.edit'));
});

test('non-admins bounced off the dashboard still land there after logging in', function () {
    $user = User::factory()->create();

    $this->get('/dashboard')->assertRedirect(route('login'));

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect('/dashboard');
});

test('non-admins are redirected to the dashboard after logging in', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect('/dashboard');
});

test('the dashboard has no stock Filament widgets', function () {
    $html = $this->actingAs(User::factory()->admin()->create())
        ->get('/admin')
        ->assertSuccessful()
        ->getContent();

    expect($html)->not->toContain('filament-widgets-account-widget')
        ->and($html)->not->toContain('filament-widgets-filament-info-widget');
});

test('the panel content spans the full width', function () {
    expect(Filament\Facades\Filament::getPanel('admin')->getMaxContentWidth())
        ->toBe(Width::Full);
});

test('the panel sidebar is a fifth narrower than the Filament default', function () {
    // The width feeds the layout's --sidebar-width custom property, so this
    // pins the rendered menu width rather than a config value. The Filament
    // default is 20rem.
    expect(Filament\Facades\Filament::getPanel('admin')->getSidebarWidth())
        ->toBe('16rem');
});

test('the panel navigation links back to the website', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin')
        ->assertSuccessful()
        ->assertSee('Return to website');
});

test('the topbar has no global search field', function () {
    expect(Filament\Facades\Filament::getPanel('admin')->getGlobalSearchProvider())->toBeNull();

    $html = $this->actingAs(User::factory()->admin()->create())
        ->get('/admin')
        ->assertSuccessful()
        ->getContent();

    expect($html)->not->toContain('fi-global-search');
});

test('the panel menu shows the configured business name', function () {
    app(Settings::class)->set(SettingKey::BusinessName, 'Cromulent Widgets');

    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin')
        ->assertSuccessful()
        ->assertSee('Cromulent Widgets');
});

test('the panel brand follows a business name changed after boot', function () {
    // The panel is configured once per process, so a brand resolved eagerly at
    // boot would keep serving the value stored then.
    $panel = Filament\Facades\Filament::getPanel('admin');

    expect($panel->getBrandName())->toBe(config('app.name'));

    app(Settings::class)->set(SettingKey::BusinessName, 'Cromulent Widgets');
    app()->forgetScopedInstances();

    expect($panel->getBrandName())->toBe('Cromulent Widgets');
});

test('the panel brand shows the bundled mark beside the business name', function () {
    app(Settings::class)->set(SettingKey::BusinessName, 'Cromulent Widgets');

    // In-order searching starts from the mark, so the name it finds after it
    // is the lockup's copy, not the one in <title> up in the head.
    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin')
        ->assertSuccessful()
        ->assertSeeInOrder(['app-logo-', 'Cromulent Widgets']);
});

test('a stored logo becomes the panel brand mark', function () {
    Storage::fake('public');

    $this->storeLogo('logo/abc');

    app(Settings::class)->set(SettingKey::BusinessName, 'Cromulent Widgets');

    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin')
        ->assertSuccessful()
        ->assertSeeInOrder(['/storage/logo/abc/mark.webp', 'Cromulent Widgets'])
        // The bundled gradient mark gives way to the upload entirely.
        ->assertDontSee('app-logo-', false);
});
