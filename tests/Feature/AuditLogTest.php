<?php

use App\Audit\AuditEvent;
use App\Audit\AuditLogger;
use App\Models\AuditLog;
use App\Models\ContactSubmission;
use App\Models\Page;
use App\Models\User;
use App\Settings\SettingKey;
use App\Settings\Settings;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Model events
|--------------------------------------------------------------------------
*/

test('creating an audited model records an entry', function () {
    $page = Page::factory()->create(['title' => 'Privacy']);

    $entry = AuditLog::query()->ofEvent(AuditEvent::Created)->forRecord($page)->sole();

    expect($entry->new_values)->toHaveKey('title', 'Privacy')
        ->and($entry->old_values)->toBe([]);
});

test('updating an audited model records only what changed', function () {
    $page = Page::factory()->create(['title' => 'Privacy']);

    $page->update(['title' => 'Privacy Policy']);

    $entry = AuditLog::query()->ofEvent(AuditEvent::Updated)->forRecord($page)->sole();

    expect($entry->new_values)->toHaveKey('title', 'Privacy Policy')
        ->and($entry->old_values)->toHaveKey('title', 'Privacy')
        // The body did not change, so it must not appear: an entry listing
        // every attribute makes the one that changed impossible to find.
        ->and($entry->new_values)->not->toHaveKey('body');
});

test('a save that changes nothing records no entry', function () {
    $page = Page::factory()->create();

    AuditLog::query()->delete();

    $page->touch();
    $page->update(['title' => $page->title]);

    expect(AuditLog::query()->ofEvent(AuditEvent::Updated)->count())->toBe(0);
});

test('deleting an audited model records the record as it was', function () {
    $page = Page::factory()->create(['title' => 'Terms']);
    $id = $page->id;

    $page->delete();

    $entry = AuditLog::query()->ofEvent(AuditEvent::Deleted)->sole();

    expect($entry->auditable_id)->toBe($id)
        // The attributes are captured on the way out; without them a delete
        // entry names an id that resolves to nothing.
        ->and($entry->old_values)->toHaveKey('title', 'Terms');
});

test('the acting user is recorded on the entry', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin);

    $page = Page::factory()->create();

    $entry = AuditLog::query()->ofEvent(AuditEvent::Created)->forRecord($page)->sole();

    expect($entry->user_id)->toBe($admin->id)
        ->and($entry->user_name)->toBe($admin->name)
        ->and($entry->user_email)->toBe($admin->email);
});

/*
 * The reason the actor is denormalised at all. A foreign key alone would go
 * null here and the entry would read "somebody did this".
 */
test('an entry still names its actor after the account is deleted', function () {
    $admin = User::factory()->admin()->create(['name' => 'Vanished Admin']);

    $this->actingAs($admin);
    $page = Page::factory()->create();

    $entry = AuditLog::query()->ofEvent(AuditEvent::Created)->forRecord($page)->sole();

    auth()->logout();
    $admin->delete();

    $entry->refresh();

    expect($entry->user_id)->toBeNull()
        ->and($entry->actorName())->toBe('Vanished Admin')
        ->and($entry->exists)->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Redaction
|--------------------------------------------------------------------------
*/

test('a password is never written to the audit log', function () {
    $user = User::factory()->create();

    $user->update(['password' => 'a-brand-new-password']);

    $entries = AuditLog::query()->forRecord($user)->get();

    expect($entries)->not->toBeEmpty();

    foreach ($entries as $entry) {
        expect($entry->new_values ?? [])->not->toHaveKey('password')
            ->and($entry->old_values ?? [])->not->toHaveKey('password')
            ->and($entry->new_values ?? [])->not->toHaveKey('remember_token')
            ->and($entry->new_values ?? [])->not->toHaveKey('two_factor_secret');
    }
});

test('the global denylist applies even when the caller passes a secret', function () {
    $user = User::factory()->create();

    // The factory's own create() already wrote a Created entry; clearing it
    // leaves the one this test is about.
    AuditLog::query()->delete();

    // Straight at the logger, bypassing the trait's own filtering, which is
    // exactly what a new write site would do.
    app(AuditLogger::class)->recordModelEvent(
        AuditEvent::Updated,
        $user,
        ['password' => 'old-hash'],
        ['password' => 'new-hash', 'name' => 'Kept'],
    );

    $entry = AuditLog::query()->latest('id')->sole();

    expect($entry->new_values)->toBe(['name' => 'Kept'])
        ->and($entry->old_values)->toBe([]);
});

/*
 * Redaction has to run BEFORE the decision to write, not after it. The trait's
 * own "did anything change" guard sees a non-empty set here -- the password
 * did change -- so only the logger can tell that nothing survives filtering.
 */
test('an update whose every changed column is redacted writes no entry', function () {
    $user = User::factory()->create();

    AuditLog::query()->delete();

    $user->update(['password' => 'a-brand-new-password']);

    expect(AuditLog::query()->ofEvent(AuditEvent::Updated)->count())->toBe(0);
});

/*
 * The common case of the above, and the reason it matters: Laravel regenerates
 * remember_token on every remember-me sign-in, so without the guard each one
 * writes a contentless row into the fastest-growing table in the application.
 */
test('a remember token being regenerated writes no entry', function () {
    $user = User::factory()->create();

    AuditLog::query()->delete();

    $user->forceFill(['remember_token' => Str::random(60)])->save();

    expect(AuditLog::query()->ofEvent(AuditEvent::Updated)->count())->toBe(0);
});

/*
 * Contact submissions are somebody else's personal data, and an audit entry
 * outlives the row it describes and cannot be deleted by anyone. Copying the
 * message body into one would mean deleting a submission no longer erases the
 * person who sent it.
 */
test('a contact submission does not copy the submitter into the trail', function () {
    $submission = ContactSubmission::factory()->create([
        'name' => 'Jane Public',
        'email' => 'jane@example.com',
        'message' => 'Private message body',
    ]);

    $submission->delete();

    $entries = AuditLog::query()->get();

    expect($entries)->not->toBeEmpty();

    foreach ($entries as $entry) {
        $recorded = json_encode([$entry->old_values, $entry->new_values]);

        expect($recorded)->not->toContain('Jane Public')
            ->and($recorded)->not->toContain('jane@example.com')
            ->and($recorded)->not->toContain('Private message body');
    }

    // The accountable part survives: the trail still says the record existed
    // and was deleted.
    expect(AuditLog::query()->ofEvent(AuditEvent::Deleted)->forRecord($submission)->exists())
        ->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Authentication events
|--------------------------------------------------------------------------
*/

test('a successful sign-in is recorded', function () {
    $user = User::factory()->create(['password' => 'password']);

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);

    $entry = AuditLog::query()->ofEvent(AuditEvent::Login)->sole();

    expect($entry->user_id)->toBe($user->id);
});

test('a rejected sign-in is recorded without the submitted password', function () {
    $user = User::factory()->create(['password' => 'password']);

    $this->post('/login', ['email' => $user->email, 'password' => 'not-the-password']);

    $entry = AuditLog::query()->ofEvent(AuditEvent::LoginFailed)->sole();

    expect($entry->context)->toHaveKey('email', $user->email)
        ->and($entry->context)->toHaveKey('account_exists', true)
        // $event->credentials carries the submitted password in plain text;
        // recording the array wholesale would store it.
        ->and(json_encode($entry->context))->not->toContain('not-the-password');
});

test('a rejected sign-in for an unknown address records that no account exists', function () {
    $this->post('/login', ['email' => 'nobody@example.com', 'password' => 'whatever']);

    $entry = AuditLog::query()->ofEvent(AuditEvent::LoginFailed)->sole();

    expect($entry->context)->toHaveKey('account_exists', false);
});

test('a sign-out is recorded', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/logout');

    expect(AuditLog::query()->ofEvent(AuditEvent::Logout)->where('user_id', $user->id)->exists())
        ->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Settings
|--------------------------------------------------------------------------
*/

test('a settings change is recorded with its before and after', function () {
    app(Settings::class)->set(SettingKey::BusinessName, 'New Name');

    $entry = AuditLog::query()->ofEvent(AuditEvent::SettingsUpdated)->sole();

    expect($entry->context['settings'])->toHaveKey(SettingKey::BusinessName->value)
        ->and($entry->context['settings'][SettingKey::BusinessName->value]['to'])->toBe('New Name');
});

test('a settings write that changes nothing records no entry', function () {
    $settings = app(Settings::class);

    $settings->set(SettingKey::BusinessName, 'Same Name');
    AuditLog::query()->delete();

    $settings->set(SettingKey::BusinessName, 'Same Name');

    expect(AuditLog::query()->ofEvent(AuditEvent::SettingsUpdated)->count())->toBe(0);
});

/*
 * The mail password is the setting most likely to end up copied into a table
 * every administrator can read, and kept there for the retention period.
 */
test('a secret setting records that it changed but not its value', function () {
    app(Settings::class)->set(SettingKey::MailPassword, 'super-secret-value');

    $entry = AuditLog::query()->ofEvent(AuditEvent::SettingsUpdated)->sole();

    expect($entry->context['settings'][SettingKey::MailPassword->value])
        ->toBe(['redacted' => true])
        ->and(json_encode($entry->context))->not->toContain('super-secret-value');
});

/*
|--------------------------------------------------------------------------
| Request context
|--------------------------------------------------------------------------
*/

/*
 * The container always has a `request` binding, console included, where Laravel
 * synthesises one from argv and answers 127.0.0.1 / "Symfony". Recording that
 * invents a client for a scheduled command that had none, and an administrator
 * reading the trail cannot tell it from a real localhost request.
 */
test('an entry written outside a request names no client', function () {
    // app()->bound('request') is true here, as it is in every console process,
    // and the synthesised request even carries REMOTE_ADDR 127.0.0.1 -- that is
    // exactly the check that used to let a fabricated client through. No route
    // has been resolved, which is what actually distinguishes the two.
    expect(app()->bound('request'))->toBeTrue()
        ->and(request()->route())->toBeNull();

    $page = Page::factory()->create();

    $entry = AuditLog::query()->ofEvent(AuditEvent::Created)->forRecord($page)->sole();

    expect($entry->ip_address)->toBeNull()
        ->and($entry->user_agent)->toBeNull();
});

test('an entry written from a request records the client', function () {
    $user = User::factory()->create(['password' => 'password']);

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);

    $entry = AuditLog::query()->ofEvent(AuditEvent::Login)->sole();

    expect($entry->ip_address)->not->toBeNull()
        ->and($entry->user_agent)->not->toBeNull();
});

/*
|--------------------------------------------------------------------------
| The switch
|--------------------------------------------------------------------------
*/

test('nothing is recorded while auditing is switched off', function () {
    config()->set('audit.enabled', false);
    // The logger reads the switch once per instance, and it is scoped -- so a
    // config change mid-request needs the binding rebuilt, exactly as a queue
    // worker rebuilds it between jobs.
    app()->forgetInstance(AuditLogger::class);

    Page::factory()->create();

    expect(AuditLog::query()->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Failure handling
|--------------------------------------------------------------------------
*/

/*
 * An audit write must never be the reason a real action fails. If the table is
 * missing -- a half-run deploy -- a page save must still succeed.
 */
test('a failing audit write does not break the action it records', function () {
    Schema::drop('audit_logs');

    $page = Page::factory()->create(['title' => 'Still Saved']);

    expect($page->exists)->toBeTrue()
        ->and(Page::query()->where('title', 'Still Saved')->exists())->toBeTrue();
});
