<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;

/**
 * Delete audit entries older than the configured retention.
 *
 * Nothing else trims this table. It takes a row per model change, sign-in and
 * failed sign-in attempt, so on a busy instance it is the fastest-growing
 * table in the application -- and a failed-sign-in burst writes thousands in
 * minutes. Left alone it grows for the life of the deployment, which is the
 * same failure the daily log rotation exists to prevent (.ai/rules/logging.md).
 *
 * Retention is time-based and blind to content, deliberately: a prune that
 * could be pointed at particular entries would be a way to edit the trail.
 * See App\Policies\AuditLogPolicy.
 *
 * Registered on the schedule in routes/console.php.
 */
class PruneAuditLog extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:prune-audit-log
                            {--days= : Override the configured retention period}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete audit log entries past the configured retention period';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = $this->retentionDays();

        // Null or zero means keep everything. Treated as a valid configuration
        // rather than an error: an instance under a legal hold wants exactly
        // this, and the command still runs on the schedule as a no-op.
        if ($days === null) {
            $this->components->info('Audit log retention is disabled; keeping all entries.');

            return self::SUCCESS;
        }

        $cutoff = now()->subDays($days);

        // Chunked rather than one DELETE: a first prune on an instance that has
        // been running for a year can match millions of rows, and a single
        // statement that large holds locks long enough to stall writes on the
        // same table -- which are sign-ins.
        $deleted = 0;

        do {
            $batch = AuditLog::query()
                ->where('created_at', '<', $cutoff)
                ->limit(1000)
                ->delete();

            $deleted += $batch;
        } while ($batch > 0);

        $this->components->info("Pruned {$deleted} audit log ".str('entry')->plural($deleted).'.');

        return self::SUCCESS;
    }

    /**
     * The retention period in days, or null to keep entries indefinitely.
     *
     * A non-numeric or negative configured value is treated as "keep
     * everything" rather than being coerced: a typo in AUDIT_RETENTION_DAYS
     * must not silently become a cutoff that deletes the trail.
     */
    private function retentionDays(): ?int
    {
        $option = $this->option('days');

        // Compared against null and '' rather than type-checked: the option
        // arrives as a string from the shell but as whatever was passed when
        // the command is called programmatically ($this->artisan(..., ['--days'
        // => 30])), and an is_string() guard silently ignores the latter.
        $configured = $option === null || $option === ''
            ? config('audit.retention_days')
            : $option;

        if (! is_numeric($configured)) {
            return null;
        }

        $days = (int) $configured;

        return $days > 0 ? $days : null;
    }
}
