<?php

use App\Models\User;
use Illuminate\Support\Facades\Storage;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('logging out returns to the home page', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('logout'))
        ->assertRedirect('/');

    $this->assertGuest();
});

/**
 * The dashboard is the ORDINARY user's landing page, not an admin screen, so it
 * links only to things any authenticated user may do to their own account.
 */
test('the dashboard greets the user and links to their own settings', function () {
    $user = User::factory()->create(['name' => 'Ada Lovelace']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Welcome back, Ada Lovelace')
        ->assertSee(route('profile.edit'))
        ->assertSee(route('security.edit'))
        ->assertSee(route('appearance.edit'));
});

/**
 * These shipped with the Livewire starter kit and point at Laravel's own repo
 * and docs. They are not this application's, and users are not its developers.
 */
test('the starter kit repository and documentation links are gone', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('github.com/laravel/livewire-starter-kit')
        ->assertDontSee('laravel.com/docs/starter-kits');
});

/**
 * The signed-in shell is blue-themed. Assert against the sidebar element itself
 * rather than the page: the mobile header carries the same tint, so a looser
 * check still passes when only the sidebar is reverted to neutral zinc.
 */
test('the dashboard menu is blue themed', function () {
    $html = $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->getContent();

    expect($html)->toMatch('/<ui-sidebar[^>]*\bbg-blue-50\/70\b/')
        ->and($html)->toMatch('/<ui-sidebar[^>]*\bborder-blue-100\b/');

    /**
     * The layout hardcodes <html class="dark">, so the dark variants are the
     * ones users actually see; leaving them on Flux's zinc renders a sidebar
     * indistinguishable from the unthemed one.
     */
    expect($html)->toMatch('/<ui-sidebar[^>]*\bdark:bg-blue-950\b/')
        ->and($html)->not->toMatch('/<ui-sidebar[^>]*\bdark:bg-zinc-900\b/');

    /** The current nav item's blue active state. */
    expect($html)->toContain('dark:data-current:bg-blue-500/25')
        ->and($html)->toContain('hover:text-blue-700');
});

/**
 * The user menu (profile dropdown) matches the blue sidebar. Flux hardcodes zinc
 * for the menu surface, item hover and separator line, so these are !important
 * overrides — assert on the elements themselves so a dropped override fails here
 * rather than rendering a stray zinc panel inside a blue sidebar.
 */
test('the dashboard user menu is blue themed', function () {
    $html = $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->getContent();

    /**
     * Both the desktop and the mobile dropdown panels. Match <ui-menu> exactly:
     * <ui-menu-radio-group> also carries a data-flux-menu* attribute.
     */
    preg_match_all('/<ui-menu\s[^>]*>/', $html, $menus);
    $panels = $menus[0];

    expect($panels)->toHaveCount(2);

    foreach ($panels as $panel) {
        expect($panel)->toContain('bg-blue-50/95!')
            ->and($panel)->toContain('border-blue-100!')
            /** Dark is the rendered mode; see the sidebar test above. */
            ->and($panel)->toContain('dark:bg-blue-900!');
    }

    /** The separator line itself, not menu.separator's wrapper. */
    preg_match_all('/<div[^>]*data-flux-separator[^>]*>/', $html, $separators);

    expect($separators[0])->not->toBeEmpty();

    foreach ($separators[0] as $separator) {
        expect($separator)->toContain('bg-blue-200!');
    }

    /** Item hover/active state. */
    expect($html)->toContain('data-active:bg-blue-500/10!');
});

/**
 * Both menus render the same avatar markup, so simply asserting the URL appears
 * somewhere in the response passes while one menu is still initials-only --
 * which is how the mobile header button was missed. Flux nests the <img> inside
 * its own element, so the count is what distinguishes the sites: the mobile
 * header button, the mobile dropdown, and the desktop menu are three renders.
 */
test('every user menu shows the uploaded avatar', function () {
    Storage::fake('public');

    $user = User::factory()->create(['avatar_path' => 'avatars/1/abc']);

    Storage::disk('public')->put('avatars/1/abc/thumb.webp', 'processed');

    $html = $this->actingAs($user)->get(route('dashboard'))->getContent();

    $rendered = substr_count($html, 'src="/storage/avatars/1/abc/thumb.webp"');

    // Two menus, each rendering the avatar twice: the button that opens it and
    // the identity row inside the dropdown.
    expect($rendered)->toBe(4);

    // Every one of those is circular. Flux emits data-circle="true" only when
    // the prop is set, so a site that lost it is a count short here rather than
    // silently rendering the one square avatar among four.
    expect(substr_count($html, 'data-circle="true"'))->toBe(4);
});

test('every user menu falls back to initials with no avatar', function () {
    Storage::fake('public');

    $user = User::factory()->create(['name' => 'Ada Lovelace', 'avatar_path' => null]);

    $html = $this->actingAs($user)->get(route('dashboard'))->getContent();

    expect($html)->not->toContain('/storage/avatars/')
        ->and(substr_count($html, 'AL'))->toBeGreaterThanOrEqual(4);
});
