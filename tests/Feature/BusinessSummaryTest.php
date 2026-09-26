<?php

use App\Auth\Role;
use App\Models\ContactSubmission;
use App\Models\PaymentLink;
use App\Models\User;
use App\Notifications\BusinessSummaryReport;
use App\Settings\SettingKey;
use App\Settings\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Tests\Support\Payments;

beforeEach(function () {
    Notification::fake();
    Payments::enable();
    app(Settings::class)->set(SettingKey::Timezone, 'America/Toronto');

    $this->admin = User::factory()->admin()->create();
});

/**
 * Take a sale in the week of Monday 2026-09-14, then stand at the given
 * moment, in Toronto time.
 */
function afterASaleLastWeek(string $at): void
{
    test()->travelTo(CarbonImmutable::parse('2026-09-16 14:00', 'America/Toronto'));
    Payments::payWithDemo(PaymentLink::factory()->costing(25000)->create());

    test()->travelTo(CarbonImmutable::parse($at, 'America/Toronto'));
}

test('the weekly summary goes to administrators at 8am Monday in the business timezone', function () {
    afterASaleLastWeek('2026-09-21 08:05');
    $editor = User::factory()->role(Role::Editor)->create();

    $this->artisan('app:send-business-summary')->assertSuccessful();

    Notification::assertSentTo($this->admin, BusinessSummaryReport::class, function (BusinessSummaryReport $report): bool {
        $lines = implode("\n", $report->toMail($this->admin)->introLines);

        return str_contains($lines, 'Revenue: CA$250.00, net of refunds')
            && str_contains($lines, 'the week of');
    });
    Notification::assertNotSentTo($editor, BusinessSummaryReport::class);
});

test('nothing is sent before 8am, or on another day', function (string $at) {
    afterASaleLastWeek($at);

    $this->artisan('app:send-business-summary')->assertSuccessful();

    Notification::assertNotSentTo($this->admin, BusinessSummaryReport::class);
})->with([
    'Monday 7:55 in Toronto, though 11:55 UTC' => '2026-09-21 07:55',
    'Tuesday' => '2026-09-22 09:00',
]);

test('each week is sent once however often the command runs', function () {
    afterASaleLastWeek('2026-09-21 08:05');

    $this->artisan('app:send-business-summary');
    $this->travel(1)->hour();
    $this->artisan('app:send-business-summary');

    Notification::assertSentToTimes($this->admin, BusinessSummaryReport::class, 1);
});

test('a redeploy that clears the cache does not send the week again', function () {
    afterASaleLastWeek('2026-09-21 08:05');

    $this->artisan('app:send-business-summary');
    // Every container start runs optimize:clear, which clears the cache.
    $this->artisan('cache:clear');
    $this->travel(2)->hours();
    $this->artisan('app:send-business-summary');

    Notification::assertSentToTimes($this->admin, BusinessSummaryReport::class, 1);
});

test('a forced send counts as the week\'s summary', function () {
    afterASaleLastWeek('2026-09-21 08:05');

    $this->artisan('app:send-business-summary', ['--force' => true]);
    $this->travel(1)->hour();
    $this->artisan('app:send-business-summary');

    Notification::assertSentToTimes($this->admin, BusinessSummaryReport::class, 1);
});

test('forcing a summary that is switched off says so', function () {
    app(Settings::class)->set(SettingKey::SummaryEmailEnabled, false);

    $this->artisan('app:send-business-summary', ['--force' => true])
        ->expectsOutputToContain('switched off')
        ->assertSuccessful();
});

test('a quiet week sends nothing', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-21 08:05', 'America/Toronto'));

    $this->artisan('app:send-business-summary')->assertSuccessful();

    Notification::assertNothingSent();
});

test('switching the summary off stops it', function () {
    afterASaleLastWeek('2026-09-21 08:05');
    app(Settings::class)->set(SettingKey::SummaryEmailEnabled, false);

    $this->artisan('app:send-business-summary')->assertSuccessful();

    Notification::assertNotSentTo($this->admin, BusinessSummaryReport::class);
});

test('the monthly summary goes out on the 1st, about the month before', function () {
    app(Settings::class)->set(SettingKey::SummaryEmailFrequency, 'monthly');
    afterASaleLastWeek('2026-10-01 09:00');

    $this->artisan('app:send-business-summary')->assertSuccessful();

    Notification::assertSentTo($this->admin, BusinessSummaryReport::class, fn (BusinessSummaryReport $report): bool => $report->summary->periodLabel === 'September 2026'
        && $report->summary->netRevenue?->amount === 25000);
});

test('with payments off it still reports customers and messages, without money', function () {
    Payments::enable(['payments_enabled' => false]);
    $this->travelTo(CarbonImmutable::parse('2026-09-21 08:05', 'America/Toronto'));
    ContactSubmission::factory()->create(['handled_at' => null]);

    $this->artisan('app:send-business-summary')->assertSuccessful();

    Notification::assertSentTo($this->admin, BusinessSummaryReport::class, function (BusinessSummaryReport $report): bool {
        $lines = implode("\n", $report->toMail($this->admin)->introLines);

        return ! str_contains($lines, 'Revenue')
            && str_contains($lines, '1 contact message is unanswered');
    });
});
