<?php

use App\Audit\AuditEvent;
use App\Filament\Pages\ManageSettings;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\Payments\PaymentCredentialsChanged;
use App\Settings\SettingKey;
use App\Settings\Settings;
use Illuminate\Encryption\Encrypter;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Notification;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

const LIVE_SECRET = 'sk_live_51SuperSecretValue9876';

beforeEach(function () {
    $this->admin = User::factory()->admin()->create(['password' => 'correct-password']);
    $this->actingAs($this->admin);
    app(Settings::class)->set(SettingKey::OpsAlertEmail, 'ops@example.test');
});

/**
 * Submit the Stripe credentials modal.
 *
 * @param  array<string, mixed>  $data
 */
function submitStripeCredentials(array $data): Testable
{
    return Livewire::test(ManageSettings::class)->callAction('stripeCredentials', $data);
}

test('a payment credential is stored encrypted, not as typed', function () {
    app(Settings::class)->set(SettingKey::StripeLiveSecretKey, LIVE_SECRET);

    $stored = Setting::query()->where('key', 'stripe_live_secret_key')->value('value');

    expect($stored)->not->toContain(LIVE_SECRET)
        ->and(Crypt::decryptString($stored))->toBe(LIVE_SECRET)
        ->and(app(Settings::class)->string(SettingKey::StripeLiveSecretKey))->toBe(LIVE_SECRET);
});

test('a stored secret is shown only masked', function () {
    app(Settings::class)->set(SettingKey::StripeLiveSecretKey, LIVE_SECRET);

    expect(app(Settings::class)->masked(SettingKey::StripeLiveSecretKey))->toBe('sk_live_…9876');
});

test('a secret never reaches the browser, in the page or in the Livewire payload', function () {
    app(Settings::class)->set(SettingKey::StripeLiveSecretKey, LIVE_SECRET);

    $modal = Livewire::test(ManageSettings::class)
        ->mountAction('stripeCredentials')
        ->assertActionMounted('stripeCredentials')
        // The secret's field opens empty; the plain ones would be filled.
        ->assertActionDataSet(['stripe_live_secret_key' => null]);

    expect($modal->html())->not->toContain(LIVE_SECRET)
        ->and(json_encode($modal->snapshot))->not->toContain(LIVE_SECRET);
});

test('saving credentials requires the administrator\'s password', function () {
    submitStripeCredentials(['stripe_live_secret_key' => 'sk_live_new', 'current_password' => 'wrong-password'])
        ->assertHasActionErrors(['current_password']);

    expect(app(Settings::class)->string(SettingKey::StripeLiveSecretKey))->toBe('');
});

test('a secret left blank keeps the one already stored', function () {
    app(Settings::class)->set(SettingKey::StripeLiveSecretKey, LIVE_SECRET);

    submitStripeCredentials(['stripe_live_webhook_secret' => 'whsec_new', 'current_password' => 'correct-password'])
        ->assertHasNoActionErrors();

    $settings = app(Settings::class);

    expect($settings->string(SettingKey::StripeLiveSecretKey))->toBe(LIVE_SECRET)
        ->and($settings->string(SettingKey::StripeLiveWebhookSecret))->toBe('whsec_new');
});

test('changing credentials alerts operations and audits the change without the value', function () {
    Notification::fake();

    submitStripeCredentials(['stripe_live_secret_key' => LIVE_SECRET, 'current_password' => 'correct-password'])
        ->assertHasNoActionErrors();

    Notification::assertSentOnDemand(
        PaymentCredentialsChanged::class,
        fn (PaymentCredentialsChanged $notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === 'ops@example.test'
            && $notification->gateway === 'Stripe'
            && $notification->changedFields === ['Live secret key'],
    );

    $entry = AuditLog::query()->where('event', AuditEvent::SettingsUpdated->value)->latest('id')->first();

    expect(json_encode($entry?->context))->not->toContain(LIVE_SECRET)
        ->and($entry?->context['settings']['stripe_live_secret_key'])->toBe(['redacted' => true]);
});

test('submitting the modal unchanged saves nothing and alerts nobody', function () {
    Notification::fake();

    submitStripeCredentials(['current_password' => 'correct-password']);

    Notification::assertNothingSent();
});

test('credentials stored under a previous application key read as unset and are reported', function () {
    // Written with a key the application no longer has, as after an APP_KEY
    // rotation without APP_PREVIOUS_KEYS.
    $foreign = (new Encrypter(random_bytes(32), 'AES-256-CBC'))->encryptString(LIVE_SECRET);
    Setting::query()->create(['key' => 'stripe_live_secret_key', 'value' => $foreign]);

    $settings = app(Settings::class);

    expect($settings->string(SettingKey::StripeLiveSecretKey))->toBe('')
        ->and($settings->undecryptableKeys())->toBe([SettingKey::StripeLiveSecretKey]);
});
