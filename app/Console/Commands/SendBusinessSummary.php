<?php

namespace App\Console\Commands;

use App\Auth\Role;
use App\Models\User;
use App\Notifications\BusinessSummaryReport;
use App\Payments\BusinessMetrics;
use App\Payments\Data\BusinessSummary;
use App\Payments\PaymentManager;
use App\Settings\SettingKey;
use App\Settings\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Email every administrator a summary of the week or month just ended.
 *
 * Scheduled hourly rather than at a fixed time, and decides for itself
 * whether a summary is due: "8am Monday" is in the business's timezone,
 * which lives in the settings table, and the schedule in routes/console.php
 * is built on every artisan boot -- migrations on an empty database
 * included -- so it must not read the database. Due means at or after 8am
 * on the Monday (weekly) or the 1st (monthly), so a scheduler that was down
 * at eight still sends later that day.
 *
 * Each period is sent once: the period sent is recorded in the settings
 * table -- not the cache, which every container start clears -- in the same
 * transaction that queues the emails. A failure before that commits records
 * nothing, so the next hourly run tries again; a summary is never lost to a
 * claim made ahead of a send that failed.
 *
 * Registered on the schedule in routes/console.php.
 */
class SendBusinessSummary extends Command
{
    /**
     * The hour, in the business's timezone, from which a summary is due.
     */
    private const int SEND_HOUR = 8;

    protected $signature = 'app:send-business-summary {--force : Send the summary of the last full period now, whether or not it is due}';

    protected $description = 'Email administrators the weekly or monthly business summary when one is due';

    public function handle(Settings $settings, BusinessMetrics $metrics, PaymentManager $payments): int
    {
        if (! $settings->boolean(SettingKey::SummaryEmailEnabled)) {
            if ($this->option('force')) {
                $this->components->warn('The business summary is switched off in Settings → Email; nothing was sent.');
            }

            return self::SUCCESS;
        }

        $frequency = $settings->string(SettingKey::SummaryEmailFrequency) === 'monthly' ? 'monthly' : 'weekly';
        $now = $metrics->now();

        if (! $this->option('force') && ! $this->isDue($frequency, $now)) {
            return self::SUCCESS;
        }

        [$from, $to, $previousFrom] = $this->period($frequency, $now);
        $period = "{$frequency}:{$from->toDateString()}";

        // A forced send records the period too, so the scheduled run that
        // follows does not send the same one again.
        if (! $this->option('force') && $settings->string(SettingKey::SummaryEmailLastPeriod) === $period) {
            return self::SUCCESS;
        }

        $summary = $this->summarize($frequency, $from, $to, $previousFrom, $settings, $metrics, $payments);
        $administrators = User::query()->withRole(Role::Admin)->get();

        // Queued on the database connection, so the notification jobs and
        // the record of the period commit together or not at all.
        DB::transaction(function () use ($summary, $administrators, $settings, $period): void {
            if ($summary->hasActivity()) {
                Notification::send($administrators, new BusinessSummaryReport($summary));
            }

            $settings->set(SettingKey::SummaryEmailLastPeriod, $period);
        });

        if (! $summary->hasActivity()) {
            $this->components->info('Nothing happened this period; no summary sent.');

            return self::SUCCESS;
        }

        $this->components->info("Sent the {$frequency} summary to {$administrators->count()} ".str('administrator')->plural($administrators->count()).'.');

        return self::SUCCESS;
    }

    /**
     * @param  'weekly'|'monthly'  $frequency
     */
    private function isDue(string $frequency, CarbonImmutable $now): bool
    {
        $isSendDay = $frequency === 'monthly' ? $now->day === 1 : $now->isMonday();

        return $isSendDay && $now->hour >= self::SEND_HOUR;
    }

    /**
     * The period just ended and the one before it, as business-calendar
     * boundaries: [start, end, previous start]. The previous period ends
     * where this one starts.
     *
     * @param  'weekly'|'monthly'  $frequency
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: CarbonImmutable}
     */
    private function period(string $frequency, CarbonImmutable $now): array
    {
        if ($frequency === 'monthly') {
            $to = $now->startOfMonth();

            return [$to->subMonthNoOverflow(), $to, $to->subMonthsNoOverflow(2)];
        }

        $to = $now->startOfWeek(CarbonImmutable::MONDAY);

        return [$to->subWeek(), $to, $to->subWeeks(2)];
    }

    /**
     * @param  'weekly'|'monthly'  $frequency
     */
    private function summarize(string $frequency, CarbonImmutable $from, CarbonImmutable $to, CarbonImmutable $previousFrom, Settings $settings, BusinessMetrics $metrics, PaymentManager $payments): BusinessSummary
    {
        $money = $payments->enabled();

        return new BusinessSummary(
            frequency: $frequency,
            periodLabel: $frequency === 'monthly'
                ? $settings->formatMonth($from)
                : __('summary.week_of', ['date' => $settings->formatDate($from)]),
            netRevenue: $money ? $metrics->netRevenue($from->utc(), $to->utc()) : null,
            previousNetRevenue: $money ? $metrics->netRevenue($previousFrom->utc(), $from->utc()) : null,
            refunded: $money ? $metrics->refunded($from->utc(), $to->utc()) : null,
            newCustomers: $metrics->newCustomers($from->utc(), $to->utc()),
            activeSubscriptions: $money ? $metrics->activeSubscriptions() : null,
            monthlyRecurringRevenue: $money ? $metrics->monthlyRecurringRevenue() : null,
            attention: $metrics->attentionCounts(),
        );
    }
}
