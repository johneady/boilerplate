<?php

use App\Settings\SettingKey;
use App\Settings\Settings;

/**
 * Fill the contact details the way an administrator would, so the rendered
 * footer reflects stored settings rather than the seeder's demo values.
 */
function storeBusinessDetails(): void
{
    $settings = app(Settings::class);

    $settings->set(SettingKey::BusinessAddress, "1 Cromulent Way\nWidgetton");
    $settings->set(SettingKey::BusinessPhone, '+1 (555) 123-4567');
    $settings->set(SettingKey::BusinessEmail, 'hello@cromulent.test');

    app()->forgetInstance(Settings::class);
}

test('the home page footer shows the stored business details', function () {
    storeBusinessDetails();

    $this->get('/')
        ->assertSuccessful()
        ->assertSee('1 Cromulent Way')
        ->assertSee('Widgetton')
        ->assertSee('+1 (555) 123-4567')
        ->assertSee('hello@cromulent.test')
        ->assertSee('href="tel:+15551234567"', escape: false)
        ->assertSee('href="mailto:hello@cromulent.test"', escape: false);
});

test('the home page footer hides the details that have not been filled in', function () {
    app(Settings::class)->set(SettingKey::BusinessPhone, '+1 (555) 123-4567');

    app()->forgetInstance(Settings::class);

    $this->get('/')
        ->assertSuccessful()
        ->assertSee('+1 (555) 123-4567')
        ->assertDontSee('mailto:')
        ->assertDontSee('123 Example Street')
        ->assertDontSee('hello@example.com');
});

test('the auth pages show the business details footer', function () {
    storeBusinessDetails();

    $this->get(route('login'))
        ->assertSuccessful()
        ->assertSee('+1 (555) 123-4567')
        ->assertSee('hello@cromulent.test');
});
