<?php

use App\Audit\AuditEvent;
use App\Models\AuditLog;

/**
 * Write an entry stamped at a given age in days.
 */
function auditEntryAgedDays(int $days): AuditLog
{
    $entry = new AuditLog;

    $entry->forceFill([
        'event' => AuditEvent::Login,
        'created_at' => now()->subDays($days),
    ])->save();

    return $entry;
}

test('entries past the retention period are deleted', function () {
    config()->set('audit.retention_days', 30);

    $old = auditEntryAgedDays(45);
    $recent = auditEntryAgedDays(5);

    $this->artisan('app:prune-audit-log')->assertSuccessful();

    expect(AuditLog::query()->whereKey($old->id)->exists())->toBeFalse()
        ->and(AuditLog::query()->whereKey($recent->id)->exists())->toBeTrue();
});

test('the retention period can be overridden per run', function () {
    config()->set('audit.retention_days', 365);

    $entry = auditEntryAgedDays(45);

    $this->artisan('app:prune-audit-log', ['--days' => 30])->assertSuccessful();

    expect(AuditLog::query()->whereKey($entry->id)->exists())->toBeFalse();
});

test('entries are kept when retention is disabled', function () {
    config()->set('audit.retention_days', 0);

    $entry = auditEntryAgedDays(4000);

    $this->artisan('app:prune-audit-log')->assertSuccessful();

    expect(AuditLog::query()->whereKey($entry->id)->exists())->toBeTrue();
});

/*
 * A typo in AUDIT_RETENTION_DAYS must not silently become a cutoff. Coercing
 * "thirty" to 0 would make every entry older than today's date eligible.
 */
test('a non-numeric retention value keeps everything rather than deleting it', function () {
    config()->set('audit.retention_days', 'thirty');

    $entry = auditEntryAgedDays(4000);

    $this->artisan('app:prune-audit-log')->assertSuccessful();

    expect(AuditLog::query()->whereKey($entry->id)->exists())->toBeTrue();
});

test('pruning deletes every eligible entry across batches', function () {
    config()->set('audit.retention_days', 30);

    // The command deletes in batches of 1000; this proves the loop continues
    // past the first batch rather than stopping at it.
    $rows = collect(range(1, 1200))->map(fn (int $i): array => [
        'event' => AuditEvent::Login->value,
        'created_at' => now()->subDays(60),
    ])->all();

    AuditLog::query()->insert($rows);

    $this->artisan('app:prune-audit-log')->assertSuccessful();

    expect(AuditLog::query()->count())->toBe(0);
});
