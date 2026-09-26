<?php

namespace Database\Seeders;

use App\Auth\Role;
use App\Models\ContactSubmission;
use App\Models\Dispute;
use App\Models\Payment;
use App\Models\PaymentLink;
use App\Models\PlanPrice;
use App\Models\TaxRate;
use App\Models\User;
use App\Payments\Actions\ReconcilePayment;
use App\Payments\Actions\ReconcileSubscription;
use App\Payments\Actions\RefundPayment;
use App\Payments\Actions\StartCheckout;
use App\Payments\Actions\StartSubscription;
use App\Payments\Data\CheckoutRequest;
use App\Payments\Drivers\DemoDriver;
use App\Payments\Enums\BillingInterval;
use App\Payments\Enums\DisputeStatus;
use App\Payments\Enums\Gateway;
use App\Payments\Enums\GatewayMode;
use App\Payments\Enums\PaymentLinkAmountType;
use App\Payments\Enums\PaymentLinkUsage;
use App\Payments\Enums\TransactionSource;
use App\Payments\Money;
use App\Payments\PaymentManager;
use App\Settings\SettingKey;
use App\Settings\Settings;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

/**
 * A year of a small business's trading, so a demo's dashboard, payments,
 * subscriptions and exports have something true to show.
 *
 * Every sale, refund and renewal goes through the real payment actions on the
 * Demo gateway, with the clock set to when it happened -- so the ledger,
 * receipt numbers, renewal periods and audit trail are exactly what a year of
 * real use would leave, not rows written to look like it. Events run in date
 * order, which is what keeps receipt numbers in sequence.
 *
 * Runs only where DatabaseSeeder offers the demo accounts (never production),
 * and only into an installation that has taken no payments, so it never
 * mixes with real ones. No factories: this runs in the --no-dev image, where
 * faker is absent (.ai/rules/seeders.md), so names come from the lists below
 * and randomness from a fixed seed, and every demo looks the same.
 */
class DemoBusinessSeeder extends Seeder
{
    /**
     * Months of trading to create, this one included.
     */
    private const int MONTHS = 12;

    /**
     * @var list<string>
     */
    private const array FIRST_NAMES = [
        'Olivia', 'Liam', 'Emma', 'Noah', 'Amelia', 'Lucas', 'Chloe', 'Ethan', 'Maya', 'Owen',
        'Zoe', 'Jack', 'Priya', 'Mateo', 'Hannah', 'Arjun', 'Grace', 'Leo', 'Aisha', 'Samuel',
    ];

    /**
     * @var list<string>
     */
    private const array LAST_NAMES = [
        'Tremblay', 'Singh', 'MacDonald', 'Nguyen', 'Roy', 'Chen', 'Campbell', 'Patel', 'Gagnon', 'Walker',
    ];

    /**
     * What the demo business sells through payment links: title, price in
     * minor units, and how often it sells relative to the others.
     *
     * @var list<array{title: string, description: string, amount: int, weight: int}>
     */
    private const array SERVICES = [
        ['title' => 'Website audit', 'description' => 'A written review of your site with a prioritised fix list.', 'amount' => 45000, 'weight' => 4],
        ['title' => 'Logo design', 'description' => 'Three concepts and two rounds of revisions.', 'amount' => 90000, 'weight' => 2],
        ['title' => 'One-hour consultation', 'description' => 'A video call about whatever you are stuck on.', 'amount' => 15000, 'weight' => 6],
        ['title' => 'Brand identity package', 'description' => 'Logo, palette, type and a one-page brand guide.', 'amount' => 250000, 'weight' => 1],
    ];

    /**
     * @var list<array{at: CarbonImmutable, run: Closure(): void}>
     */
    private array $events = [];

    public function run(): void
    {
        if (app()->environment('production') || Payment::query()->exists()) {
            return;
        }

        // DatabaseSeeder runs its seeders without model events, but the
        // payment models depend on theirs: uuids, the write-once guards on
        // financial records, the audit trail. Switched back on for this run
        // only.
        $dispatcher = Model::getEventDispatcher();
        Model::setEventDispatcher(app('events'));

        try {
            $this->seed();
        } finally {
            $dispatcher === null ? Model::unsetEventDispatcher() : Model::setEventDispatcher($dispatcher);
        }
    }

    private function seed(): void
    {
        $admin = User::query()->where('email', config('first.user.email'))->first();

        if ($admin === null) {
            return;
        }

        mt_srand(2026);

        // Receipts, renewal notices and operator alerts would otherwise go
        // out by the hundred to invented addresses; the tax-rate sync job
        // has no gateway to talk to. Restored afterwards, so the rest of the
        // process behaves normally.
        $originals = [Notification::getFacadeRoot(), Mail::getFacadeRoot(), Queue::getFacadeRoot()];
        Notification::fake();
        Mail::fake();
        Queue::fake();

        try {
            $this->switchOnDemoPayments();

            if (! $this->demoPaymentsAreOn()) {
                return;
            }

            // All or nothing: the entrypoint seeds on every deploy, and a
            // half-seeded year -- customers saved, sales failed -- would fail
            // every later deploy on the users.email unique index.
            DB::transaction(function () use ($admin): void {
                $this->seedTaxRate();

                $customers = $this->seedCustomers();
                $links = $this->seedPaymentLinks();

                $this->scheduleSales($links, $customers, $admin);
                $this->scheduleSubscriptions($customers);

                usort($this->events, fn (array $a, array $b): int => $a['at'] <=> $b['at']);

                foreach ($this->events as $event) {
                    Date::setTestNow($event['at']);
                    ($event['run'])();
                }

                Date::setTestNow();

                $this->seedDispute();
                $this->seedMessages();
            });
        } finally {
            Date::setTestNow();
            Notification::swap($originals[0]);
            Mail::swap($originals[1]);
            Queue::swap($originals[2]);
        }
    }

    /**
     * Whether demo sales can be taken: payments on, in sandbox, with the
     * Demo gateway offered.
     *
     * Checked rather than assumed, because an operator may have configured
     * payments before the first seed: a year of pretend revenue must never
     * land in live mode and use up the live receipt numbers, and a gateway
     * switched off would refuse every checkout.
     */
    private function demoPaymentsAreOn(): bool
    {
        $payments = app(PaymentManager::class);

        return $payments->enabled()
            && $payments->mode() === GatewayMode::Sandbox
            && app(Settings::class)->boolean(SettingKey::DemoGatewayEnabled);
    }

    /**
     * Turn payments on in sandbox with the Demo gateway, unless someone has
     * already configured them -- a demo is walked through, and a dashboard
     * with payments off shows no money at all.
     */
    private function switchOnDemoPayments(): void
    {
        $settings = app(Settings::class);

        if ($settings->has(SettingKey::PaymentsEnabled)) {
            return;
        }

        $settings->setMany([
            SettingKey::PaymentsEnabled->value => true,
            SettingKey::PaymentsMode->value => 'sandbox',
            SettingKey::DemoGatewayEnabled->value => true,
            SettingKey::ManualPaymentsEnabled->value => true,
        ]);
    }

    private function seedTaxRate(): void
    {
        if (TaxRate::query()->exists()) {
            return;
        }

        $rate = new TaxRate;
        $rate->name = 'HST';
        $rate->percentage = '13';
        $rate->registration_number = '123456789 RT0001';
        $rate->is_active = true;
        $rate->save();
    }

    /**
     * Forty customers who signed up across the year, more of them lately.
     *
     * @return list<User>
     */
    private function seedCustomers(): array
    {
        $customers = [];

        for ($i = 0; $i < 40; $i++) {
            $first = self::FIRST_NAMES[$i % count(self::FIRST_NAMES)];
            $last = self::LAST_NAMES[intdiv($i, 2) % count(self::LAST_NAMES)];

            // Squared, so sign-ups bunch towards the present: a business
            // that is growing.
            $daysAgo = (int) round((1 - sqrt($i / 40)) * self::MONTHS * 30) + 1;
            $joined = $this->randomTime(CarbonImmutable::now()->subDays($daysAgo));

            $user = new User;
            $user->name = "{$first} {$last}";
            $user->email = Str::lower("{$first}.{$last}.{$i}@example.com");
            $user->password = Str::password(32);
            $user->role = Role::User;
            $user->email_verified_at = $joined;
            $user->created_at = $joined;
            $user->updated_at = $joined;
            $user->save();

            $customers[] = $user;
        }

        return $customers;
    }

    /**
     * @return list<array{link: PaymentLink, weight: int}>
     */
    private function seedPaymentLinks(): array
    {
        $currency = app(PaymentManager::class)->currency();

        return array_map(function (array $service) use ($currency): array {
            $link = new PaymentLink;
            $link->title = $service['title'];
            $link->description = $service['description'];
            $link->amount_type = PaymentLinkAmountType::Fixed;
            $link->amount = $service['amount'];
            $link->currency = $currency;
            $link->taxable = true;
            $link->usage = PaymentLinkUsage::Reusable;
            $link->is_active = true;
            $link->save();

            return ['link' => $link, 'weight' => $service['weight']];
        }, self::SERVICES);
    }

    /**
     * One-off sales, rising month on month, with the odd declined card and
     * a few refunds a couple of days after the sale.
     *
     * @param  list<array{link: PaymentLink, weight: int}>  $links
     * @param  list<User>  $customers
     */
    private function scheduleSales(array $links, array $customers, User $admin): void
    {
        $pool = [];

        foreach ($links as $entry) {
            array_push($pool, ...array_fill(0, $entry['weight'], $entry['link']));
        }

        $now = CarbonImmutable::now();

        for ($ago = self::MONTHS - 1; $ago >= 0; $ago--) {
            $monthStart = $now->startOfMonth()->subMonthsNoOverflow($ago);
            // Growing by two sales a month, from about five to about
            // twenty-five: enough that the trend outweighs the odd large sale.
            $sales = 4 + 2 * (self::MONTHS - 1 - $ago) + mt_rand(0, 2);

            for ($sale = 0; $sale < $sales; $sale++) {
                $at = $this->randomTime($monthStart->addDays(mt_rand(0, $monthStart->daysInMonth - 1)));

                if ($at->greaterThan($now->subHour())) {
                    continue;
                }

                $known = array_values(array_filter($customers, fn (User $user): bool => $user->created_at <= $at));

                if ($known === []) {
                    continue;
                }

                $customer = $known[mt_rand(0, count($known) - 1)];
                $link = $pool[mt_rand(0, count($pool) - 1)];
                $key = 'demo-seed:sale:'.count($this->events);
                $outcome = mt_rand(1, 30) === 1 ? 'decline' : 'approve';

                $this->at($at, function () use ($link, $customer, $key, $outcome): void {
                    $payment = app(StartCheckout::class)->handle($link, new CheckoutRequest(
                        gateway: Gateway::Demo,
                        customerName: $customer->name,
                        customerEmail: $customer->email,
                        idempotencyKey: $key,
                        userId: $customer->id,
                    ));

                    app(DemoDriver::class)->simulateCustomer($payment, $outcome);
                    app(ReconcilePayment::class)->handle($payment->refresh(), TransactionSource::Return);
                });

                // A few days later -- but never in the future, which would date
                // ledger rows and audit entries after today.
                $refundAt = $at->addDays(mt_rand(1, 4))->min($now->subMinute());

                if ($outcome === 'approve' && mt_rand(1, 20) === 1 && $refundAt->greaterThan($at)) {
                    $this->at($refundAt, function () use ($key, $admin): void {
                        $payment = Payment::query()->where('idempotency_key', $key)->firstOrFail();
                        $amount = mt_rand(0, 1) === 1 ? $payment->total() : Money::of(intdiv($payment->amount, 4), $payment->currency);

                        app(RefundPayment::class)->handle($payment, $amount, $key.':refund', 'Customer request', $admin);
                    });
                }
            }
        }
    }

    /**
     * A dozen subscribers who joined across the year and renew monthly --
     * one of them with a card that failed at the last renewal.
     *
     * @param  list<User>  $customers
     */
    private function scheduleSubscriptions(array $customers): void
    {
        $prices = PlanPrice::query()
            ->with('plan.prices')
            ->where('is_active', true)
            ->where('interval', BillingInterval::Month->value)
            ->where('currency', app(PaymentManager::class)->currency()->value)
            ->get()
            ->all();

        if ($prices === []) {
            return;
        }

        $now = CarbonImmutable::now();
        $subscribers = array_slice($customers, 0, 12);

        foreach ($subscribers as $index => $user) {
            $price = $prices[$index % count($prices)];
            $start = CarbonImmutable::parse($user->created_at)->addDays(mt_rand(0, 10));

            if ($start->greaterThan($now->subDay())) {
                continue;
            }

            $key = "demo-seed:subscription:{$user->id}";

            $this->at($start, function () use ($user, $price, $key): void {
                $subscription = app(StartSubscription::class)->handle($user, $price, Gateway::Demo, $key);

                app(DemoDriver::class)->simulateSubscriber($subscription, 'approve');
                app(ReconcileSubscription::class)->handle($subscription->refresh(), TransactionSource::Return);
            });

            $renewals = [];

            for ($renewal = $start->addMonthNoOverflow(); $renewal->lessThan($now); $renewal = $renewal->addMonthNoOverflow()) {
                $renewals[] = $renewal;
            }

            foreach ($renewals as $position => $renewal) {
                // The last renewal of the first subscriber fails, so the demo
                // has a past-due subscription to find on the dashboard.
                $fails = $index === 0 && $position === array_key_last($renewals);

                $this->at($renewal, function () use ($user, $fails): void {
                    $subscription = $user->currentSubscription();

                    if ($subscription === null) {
                        return;
                    }

                    $driver = app(DemoDriver::class);
                    $fails ? $driver->simulateFailedRenewal($subscription) : $driver->simulateRenewal($subscription);

                    app(ReconcileSubscription::class)->handle($subscription, TransactionSource::Demo);
                });
            }
        }
    }

    /**
     * An open chargeback on a recent sale, waiting on a response.
     */
    private function seedDispute(): void
    {
        $payment = Payment::query()->whereNotNull('receipt_number')->where('gateway', Gateway::Demo)->latest('id')->first();

        if ($payment === null) {
            return;
        }

        $dispute = new Dispute;
        $dispute->payment_id = $payment->id;
        $dispute->gateway = $payment->gateway;
        $dispute->mode = $payment->mode;
        $dispute->gateway_dispute_id = 'demo_dp_'.$payment->uuid;
        $dispute->currency = $payment->currency;
        $dispute->amount = $payment->amount;
        $dispute->reason = 'product_not_received';
        $dispute->status = DisputeStatus::NeedsResponse;
        $dispute->evidence_due_by = now()->addDays(6);
        $dispute->save();
    }

    /**
     * Contact messages: a few answered, a few still waiting.
     */
    private function seedMessages(): void
    {
        $messages = [
            ['Anna Kowalski', 'anna.kowalski@example.com', 'Quote for a new site', 'Could you send a rough quote for a five-page site for my bakery?', 1, false],
            ['Ben Osei', 'ben.osei@example.com', 'Invoice question', 'My receipt shows HST twice -- is that right?', 2, false],
            ['Clara Dubois', 'clara.dubois@example.com', 'Availability next month', 'Do you have room for a logo project starting in two weeks?', 3, false],
            ['Dev Mehta', 'dev.mehta@example.com', 'Thanks!', 'The audit was really useful, thank you.', 12, true],
            ['Erin Walsh', 'erin.walsh@example.com', 'Rescheduling', 'Can we move our consultation to Thursday?', 20, true],
        ];

        foreach ($messages as [$name, $email, $subject, $body, $daysAgo, $handled]) {
            $at = $this->randomTime(now()->subDays($daysAgo)->toImmutable());

            $message = new ContactSubmission;
            $message->name = $name;
            $message->email = $email;
            $message->subject = $subject;
            $message->message = $body;
            $message->handled_at = $handled ? $at->addHours(3) : null;
            $message->created_at = $at;
            $message->updated_at = $at;
            $message->save();
        }
    }

    /**
     * Queue something to happen at a moment in the demo's past.
     *
     * @param  Closure(): void  $run
     */
    private function at(CarbonImmutable $at, Closure $run): void
    {
        $this->events[] = ['at' => $at, 'run' => $run];
    }

    /**
     * A moment during business hours on the given day.
     */
    private function randomTime(CarbonImmutable $day): CarbonImmutable
    {
        return $day->setTime(mt_rand(9, 18), mt_rand(0, 59));
    }
}
