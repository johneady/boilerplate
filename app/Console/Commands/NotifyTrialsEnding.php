<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Notifications\Payments\TrialEnding;
use App\Payments\Enums\SubscriptionStatus;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Remind subscribers that their free trial ends soon.
 *
 * Sent a few days out, once per subscription: the reminder is claimed with a
 * conditional update on trial_reminder_sent_at before it is sent, so an
 * overlapping or repeated run sends nothing twice. Done here for every
 * gateway rather than from Stripe's trial_will_end event, so Stripe and
 * PayPal subscribers are reminded the same way.
 *
 * Registered on the schedule in routes/console.php.
 */
class NotifyTrialsEnding extends Command
{
    /**
     * How far ahead of the trial's end the reminder goes.
     */
    public const int DAYS_AHEAD = 3;

    protected $signature = 'payments:notify-trials-ending';

    protected $description = 'Remind subscribers whose free trial ends within '.self::DAYS_AHEAD.' days';

    public function handle(): int
    {
        $sent = 0;

        Subscription::query()
            ->with(['user', 'plan', 'price'])
            ->where('status', SubscriptionStatus::Trialing->value)
            ->whereNull('trial_reminder_sent_at')
            // Already cancelled: nothing will be billed, so nothing to warn of.
            ->where('cancel_at_period_end', false)
            ->whereBetween('trial_ends_at', [now(), now()->addDays(self::DAYS_AHEAD)])
            ->chunkById(100, function ($trials) use (&$sent): void {
                foreach ($trials as $subscription) {
                    $claimed = Subscription::query()
                        ->whereKey($subscription->id)
                        ->whereNull('trial_reminder_sent_at')
                        ->where('cancel_at_period_end', false)
                        ->update(['trial_reminder_sent_at' => CarbonImmutable::now()]);

                    if ($claimed === 1 && $subscription->user !== null) {
                        $subscription->user->notify(new TrialEnding($subscription));
                        $sent++;
                    }
                }
            });

        $this->components->info("Sent {$sent} trial ".str('reminder')->plural($sent).'.');

        return self::SUCCESS;
    }
}
