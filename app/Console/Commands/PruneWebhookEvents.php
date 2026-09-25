<?php

namespace App\Console\Commands;

use App\Models\WebhookEvent;
use Illuminate\Console\Command;

/**
 * Delete webhook events older than the configured retention.
 *
 * Their payloads carry customers' names and email addresses, and they are
 * only messages about changes the payment rows and ledger already record, so
 * they are not kept forever. Time-based and blind to content, like the audit
 * log prune, and chunked so a first run on a busy instance does not hold one
 * long lock on the table webhooks are being written to.
 *
 * Registered on the schedule in routes/console.php.
 */
class PruneWebhookEvents extends Command
{
    protected $signature = 'payments:prune-webhook-events';

    protected $description = 'Delete webhook events past config(\'payments.webhook_retention_days\')';

    public function handle(): int
    {
        $cutoff = now()->subDays((int) config('payments.webhook_retention_days'));
        $deleted = 0;

        do {
            $batch = WebhookEvent::query()->where('created_at', '<', $cutoff)->limit(1000)->delete();
            $deleted += $batch;
        } while ($batch > 0);

        $this->components->info("Pruned {$deleted} webhook ".str('event')->plural($deleted).'.');

        return self::SUCCESS;
    }
}
