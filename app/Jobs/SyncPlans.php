<?php

namespace App\Jobs;

use App\Payments\Actions\SyncPlan;
use App\Payments\PaymentManager;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use RuntimeException;

/**
 * Sync every plan, price and tax rate out to the gateways.
 *
 * Dispatched after a plan, price or tax rate is saved. Unique only while
 * waiting in the queue, so a burst of edits (a plan and three prices saved
 * together) syncs once, while an edit saved during a running sync queues
 * another rather than being lost. The sync only calls the gateway for what is
 * missing or changed.
 */
class SyncPlans extends Job implements ShouldBeUniqueUntilProcessing
{
    public int $uniqueFor = 300;

    public function handle(SyncPlan $sync): void
    {
        $failures = $sync->all();

        if ($failures !== []) {
            // Retried with backoff; the admin panel shows what is unsynced
            // meanwhile, and the Sync action reports the gateway's words.
            throw new RuntimeException('Plans could not be synced: '.implode('; ', array_map(
                fn (string $plan, string $error): string => "{$plan}: {$error}",
                array_keys($failures),
                $failures,
            )));
        }
    }

    /**
     * Queue a sync once the surrounding transaction commits, if payments are
     * on and any gateway takes subscriptions.
     */
    public static function dispatchForCurrentMode(): void
    {
        $payments = app(PaymentManager::class);

        if ($payments->enabled() && $payments->subscriptionGateways() !== []) {
            static::dispatch()->afterCommit();
        }
    }
}
