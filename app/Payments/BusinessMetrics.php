<?php

namespace App\Payments;

use App\Auth\Role;
use App\Models\ContactSubmission;
use App\Models\Dispute;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\Subscription;
use App\Models\User;
use App\Payments\Enums\BillingInterval;
use App\Payments\Enums\SubscriptionStatus;
use App\Settings\SettingKey;
use App\Settings\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * The figures an owner asks about: what came in, who signed up, what recurs,
 * and what needs a response. Shared by the dashboard and the summary email,
 * so the two can never disagree.
 *
 * Revenue is read from the ledger (payment_transactions), which records
 * money when it actually moved -- a charge positive, a refund negative -- so
 * a period's net revenue is one sum, and a refund lands in the month it was
 * made rather than rewriting the month of the sale. Everything money-shaped
 * is for the current payments mode and currency, so sandbox tests never
 * inflate live figures.
 *
 * Periods are the business's calendar: "this month" starts at midnight on
 * the 1st in the configured timezone, not in UTC.
 */
class BusinessMetrics
{
    public function __construct(
        private readonly PaymentManager $payments,
        private readonly Settings $settings,
    ) {}

    /**
     * Money received less money refunded, between two instants.
     */
    public function netRevenue(CarbonImmutable $from, CarbonImmutable $to): Money
    {
        return Money::of((int) $this->ledger($from, $to)->sum('payment_transactions.amount'), $this->payments->currency());
    }

    /**
     * Money received before any refund, between two instants.
     */
    public function grossRevenue(CarbonImmutable $from, CarbonImmutable $to): Money
    {
        return Money::of((int) $this->ledger($from, $to)->where('payment_transactions.amount', '>', 0)->sum('payment_transactions.amount'), $this->payments->currency());
    }

    /**
     * Money refunded between two instants, as a positive amount.
     */
    public function refunded(CarbonImmutable $from, CarbonImmutable $to): Money
    {
        return Money::of(-(int) $this->ledger($from, $to)->where('payment_transactions.amount', '<', 0)->sum('payment_transactions.amount'), $this->payments->currency());
    }

    /**
     * Net revenue for each of the last $days business days, oldest first, in
     * minor units -- the shape a sparkline wants.
     *
     * @return list<int>
     */
    public function dailyNetRevenue(int $days): array
    {
        $today = $this->now()->startOfDay();
        $first = $today->subDays($days - 1);
        $buckets = array_fill(0, $days, 0);

        $rows = $this->ledger($first->utc(), $this->now()->utc())
            ->get(['payment_transactions.occurred_at', 'payment_transactions.amount']);

        foreach ($rows as $row) {
            $day = (int) $first->diffInDays(CarbonImmutable::parse($row->occurred_at)->timezone($this->timezone())->startOfDay());

            if ($day >= 0 && $day < $days) {
                $buckets[$day] += (int) $row->amount;
            }
        }

        return array_values($buckets);
    }

    /**
     * Net revenue for each of the last $months calendar months, oldest first,
     * this month included, keyed by the month's first day (Y-m-d).
     *
     * @return array<string, Money>
     */
    public function monthlyNetRevenue(int $months): array
    {
        $currency = $this->payments->currency();
        $totals = [];

        for ($ago = $months - 1; $ago >= 0; $ago--) {
            $totals[$this->monthStart($ago)->timezone($this->timezone())->toDateString()] = 0;
        }

        // One read of the period's rows, bucketed here: grouping by a month
        // in the business's timezone is not portable SQL across the SQLite,
        // MySQL and MariaDB this runs on.
        $rows = $this->ledger($this->monthStart($months - 1), $this->monthStart(-1))
            ->get(['payment_transactions.occurred_at', 'payment_transactions.amount']);

        foreach ($rows as $row) {
            $month = CarbonImmutable::parse($row->occurred_at)->timezone($this->timezone())->startOfMonth()->toDateString();

            if (array_key_exists($month, $totals)) {
                $totals[$month] += (int) $row->amount;
            }
        }

        return array_map(fn (int $minor): Money => Money::of($minor, $currency), $totals);
    }

    /**
     * Customer accounts created between two instants. Staff are not customers.
     */
    public function newCustomers(CarbonImmutable $from, CarbonImmutable $to): int
    {
        return User::query()
            ->withRole(Role::User)
            ->where('created_at', '>=', $from)
            ->where('created_at', '<', $to)
            ->count();
    }

    /**
     * Subscriptions running now: trialing, paying, or paying late.
     */
    public function activeSubscriptions(): int
    {
        return $this->subscriptions()
            ->whereIn('status', [SubscriptionStatus::Trialing->value, SubscriptionStatus::Active->value, SubscriptionStatus::PastDue->value])
            ->count();
    }

    /**
     * Monthly recurring revenue: what the paying subscriptions bring in a
     * month, a yearly price counted as a twelfth. Trials are left out --
     * they bring in nothing until they convert.
     */
    public function monthlyRecurringRevenue(): Money
    {
        $currency = $this->payments->currency();

        $monthly = $this->subscriptions()
            ->whereIn('status', [SubscriptionStatus::Active->value, SubscriptionStatus::PastDue->value])
            ->where('currency', $currency->value)
            ->with('price')
            ->get()
            ->sum(function (Subscription $subscription): int {
                $price = $subscription->price;

                if ($price === null) {
                    return 0;
                }

                $months = ($price->interval === BillingInterval::Year ? 12 : 1) * max(1, $price->interval_count);

                return intdiv($price->amount, $months);
            });

        return Money::of((int) $monthly, $currency);
    }

    /**
     * Chargebacks and claims waiting on the business's response.
     */
    public function disputesNeedingResponse(): int
    {
        return Dispute::query()->needingResponse($this->payments->mode())->count();
    }

    /**
     * Subscriptions whose renewal failed and are running on grace.
     */
    public function pastDueSubscriptions(): int
    {
        return $this->subscriptions()->where('status', SubscriptionStatus::PastDue->value)->count();
    }

    /**
     * Held payments that lapse soon unless captured -- the same window the
     * expiry warning email uses.
     */
    public function holdsExpiringSoon(): int
    {
        return Payment::query()->where('mode', $this->payments->mode()->value)->holdsExpiringSoon()->count();
    }

    /**
     * Contact form messages nobody has marked handled.
     */
    public function unhandledMessages(): int
    {
        return ContactSubmission::query()->unhandled()->count();
    }

    /**
     * The first instant of the calendar month $monthsAgo months back (0 is
     * this month, -1 next month), in UTC.
     */
    public function monthStart(int $monthsAgo = 0): CarbonImmutable
    {
        return $this->now()->startOfMonth()->subMonthsNoOverflow($monthsAgo)->utc();
    }

    /**
     * Now, in the business's timezone.
     */
    public function now(): CarbonImmutable
    {
        return CarbonImmutable::now($this->timezone());
    }

    /**
     * Ledger rows for the current mode and currency between two instants,
     * the upper bound exclusive.
     *
     * @return Builder<PaymentTransaction>
     */
    private function ledger(CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return PaymentTransaction::query()
            ->join('payments', 'payments.id', '=', 'payment_transactions.payment_id')
            ->where('payments.mode', $this->payments->mode()->value)
            ->where('payment_transactions.currency', $this->payments->currency()->value)
            ->where('payment_transactions.occurred_at', '>=', $from)
            ->where('payment_transactions.occurred_at', '<', $to);
    }

    /**
     * Subscriptions in the current mode.
     *
     * @return Builder<Subscription>
     */
    private function subscriptions(): Builder
    {
        return Subscription::query()->where('mode', $this->payments->mode()->value);
    }

    private function timezone(): string
    {
        return $this->settings->string(SettingKey::Timezone);
    }
}
