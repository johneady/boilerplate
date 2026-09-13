<?php

use App\Audit\AuditEvent;
use App\Audit\AuditLogger;
use App\Filament\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Models\AuditLog;
use App\Models\Page;
use App\Models\User;
use App\Settings\SettingKey;
use App\Settings\Settings;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
});

test('an administrator can reach the audit log screen', function () {
    $this->actingAs($this->admin)
        ->get('/admin/audit-logs')
        ->assertSuccessful();
});

test('an ordinary user cannot reach the audit log screen', function () {
    $this->actingAs(User::factory()->create())
        ->get('/admin/audit-logs')
        ->assertForbidden();
});

test('a guest is sent to the login page', function () {
    $this->get('/admin/audit-logs')->assertRedirect(route('login'));
});

test('the table lists the recorded entries', function () {
    $this->actingAs($this->admin);

    Page::factory()->count(2)->create();

    $entries = AuditLog::query()->ofEvent(AuditEvent::Created)->get();

    Livewire::test(ListAuditLogs::class)->assertCanSeeTableRecords($entries);
});

test('entries can be filtered by event', function () {
    $this->actingAs($this->admin);

    $page = Page::factory()->create();
    $created = AuditLog::query()->ofEvent(AuditEvent::Created)->forRecord($page)->sole();

    $signIn = app(AuditLogger::class)->record(AuditEvent::Login);

    Livewire::test(ListAuditLogs::class)
        ->filterTable('event', [AuditEvent::Login->value])
        ->assertCanSeeTableRecords([$signIn])
        ->assertCanNotSeeTableRecords([$created]);
});

/*
 * The column renders in the display timezone while the value is stored in UTC,
 * so a filter comparing the raw date filed entries under a different day than
 * the one they are shown as -- and an administrator narrowing to the day of an
 * incident silently missed everything within the offset of its boundary.
 */
test('the date filter agrees with the day the entry is displayed as', function () {
    app(Settings::class)->set(SettingKey::Timezone, 'America/Los_Angeles');

    $this->actingAs($this->admin);

    // 02:00 UTC on the 13th is 19:00 on the 12th in Los Angeles.
    $entry = new AuditLog;
    $entry->forceFill([
        'event' => AuditEvent::Login,
        'created_at' => CarbonImmutable::parse('2026-09-13 02:00:00', 'UTC'),
    ])->save();

    expect(app(Settings::class)->formatDateTime($entry->fresh()->created_at))
        ->toContain('12 Sep 2026');

    Livewire::test(ListAuditLogs::class)
        ->filterTable('recorded_at', ['from' => '2026-09-12', 'until' => '2026-09-12'])
        ->assertCanSeeTableRecords([$entry]);
});

test('the date filter excludes a day the entry is not displayed as', function () {
    app(Settings::class)->set(SettingKey::Timezone, 'America/Los_Angeles');

    $this->actingAs($this->admin);

    $entry = new AuditLog;
    $entry->forceFill([
        'event' => AuditEvent::Login,
        'created_at' => CarbonImmutable::parse('2026-09-13 02:00:00', 'UTC'),
    ])->save();

    // The UTC day, which is NOT the day the administrator sees.
    Livewire::test(ListAuditLogs::class)
        ->filterTable('recorded_at', ['from' => '2026-09-13', 'until' => '2026-09-13'])
        ->assertCanNotSeeTableRecords([$entry]);
});

/*
 * The immutability rules. These matter more than the display: the Gate::before
 * bypass in AuthServiceProvider answers `delete` with true for an administrator
 * on every other model, so without the exemption an admin could prune the trail
 * that records what they did.
 */
test('nobody may delete an audit entry, administrators included', function () {
    $entry = app(AuditLogger::class)->record(AuditEvent::Login);

    expect($this->admin->can('delete', $entry))->toBeFalse()
        ->and($this->admin->can('forceDelete', $entry))->toBeFalse()
        ->and($this->admin->can('delete', AuditLog::class))->toBeFalse();
});

test('nobody may create or update an audit entry', function () {
    $entry = app(AuditLogger::class)->record(AuditEvent::Login);

    expect($this->admin->can('create', AuditLog::class))->toBeFalse()
        ->and($this->admin->can('update', $entry))->toBeFalse();
});

test('an administrator may still delete other models', function () {
    // Guards the exemption's scope: standing the bypass down for `delete` on
    // the audit log must not stand it down for every model in the application.
    expect($this->admin->can('delete', Page::factory()->create()))->toBeTrue();
});

test('the audit table offers no delete actions', function () {
    $this->actingAs($this->admin);

    Livewire::test(ListAuditLogs::class)
        ->assertTableActionDoesNotExist('delete')
        ->assertTableBulkActionDoesNotExist('delete');
});
