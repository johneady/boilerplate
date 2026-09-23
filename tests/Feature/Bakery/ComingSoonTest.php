<?php

use App\Models\Page;
use App\Models\User;
use App\Settings\SettingKey;
use App\Settings\Settings;

function turnOnComingSoon(string $message = ''): void
{
    app(Settings::class)->set(SettingKey::ComingSoon, true);
    app(Settings::class)->set(SettingKey::ComingSoonMessage, $message);
}

test('the public site is served normally while coming soon is off', function () {
    $this->get('/')
        ->assertSuccessful()
        ->assertSee('The menu')
        ->assertDontSee('Our website is');
});

test('guests see the holding page on every public page while coming soon is on', function (string $path) {
    Page::factory()->published()->create(['slug' => 'about']);

    turnOnComingSoon();

    $this->get($path)
        ->assertSuccessful()
        ->assertSee('in the oven.', false)
        ->assertSee('noindex, nofollow', false)
        ->assertDontSee('Request an order');
})->with(['home' => '/', 'order' => '/order', 'contact' => '/contact', 'about page' => '/about']);

test('the holding page shows the message from the launch settings', function () {
    turnOnComingSoon('Opening for Thanksgiving orders on 1 October!');

    $this->get('/')->assertSee('Opening for Thanksgiving orders on 1 October!');
});

test('administrators see the real site behind the holding page, with a reminder', function () {
    turnOnComingSoon();

    $this->actingAs(User::factory()->admin()->create())
        ->get('/')
        ->assertSuccessful()
        ->assertSee('The menu')
        ->assertSee('Coming soon mode is on');
});

test('a signed-in customer still sees the holding page', function () {
    turnOnComingSoon();

    $this->actingAs(User::factory()->create())
        ->get('/')
        ->assertSee('in the oven.', false)
        ->assertDontSee('The menu');
});

/**
 * The owner has to be able to sign in to turn the page off again.
 */
test('login keeps working while coming soon is on', function () {
    turnOnComingSoon();

    $this->get(route('login'))->assertSuccessful()->assertDontSee('in the oven.', false);
});

test('the holding page can be previewed while coming soon is off', function () {
    $this->get(route('coming-soon'))
        ->assertSuccessful()
        ->assertSee('in the oven.', false)
        ->assertSee('Morning bake');
});
