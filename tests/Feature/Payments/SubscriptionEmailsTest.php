<?php

use App\Filament\Resources\Subscriptions\SubscriptionResource;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\Payments\SubscriptionPaymentFailed;
use App\Notifications\Payments\SubscriptionRenewed;
use App\Notifications\Payments\TrialEnding;
use App\Payments\Enums\SubscriptionStatus;
use App\Settings\Settings;
use Illuminate\Notifications\AnonymousNotifiable;
use Tests\Support\Payments;

beforeEach(function () {
    Payments::enable();
});

test('the failed-renewal email sends the subscriber to fix their payment method before access lapses', function () {
    $subscription = Subscription::factory()->status(SubscriptionStatus::PastDue)->create(['past_due_since' => now()]);

    $mail = (new SubscriptionPaymentFailed($subscription))->toMail($subscription->user);

    expect($mail->actionUrl)->toBe(route('billing.edit'))
        ->and(implode("\n", $mail->introLines))->toContain(app(Settings::class)->formatDate(now()->addDays(7)));
});

test('the operators\' copy names the customer and links to the subscription in the panel', function () {
    $subscription = Subscription::factory()->for(User::factory()->state(['email' => 'sam@example.test']))->status(SubscriptionStatus::PastDue)->create(['past_due_since' => now()]);

    $mail = (new SubscriptionPaymentFailed($subscription, forOperators: true))->toMail(new AnonymousNotifiable);

    expect(implode("\n", $mail->introLines))->toContain('sam@example.test')
        ->and($mail->actionUrl)->toBe(SubscriptionResource::getUrl('view', ['record' => $subscription], panel: 'admin'));
});

test('the subscription receipt itemises tax and says when it renews next', function () {
    $subscription = Payments::subscribeWithDemo(User::factory()->create(), PlanPrice::factory()->for(Plan::factory()->state(['name' => 'Pro']))->create());
    $payment = $subscription->payments()->sole();

    $mail = (new SubscriptionRenewed($payment))->toMail(new AnonymousNotifiable);

    expect(implode("\n", $mail->introLines))->toContain('Pro subscription')
        ->toContain('Total paid: CA$29.00')
        ->toContain(app(Settings::class)->formatDate($subscription->current_period_end))
        ->and($mail->actionUrl)->toBe($payment->receiptUrl());
});

test('the trial reminder says when billing starts and at what price', function () {
    $subscription = Subscription::factory()->status(SubscriptionStatus::Trialing)->create(['trial_ends_at' => now()->addDays(3)]);

    $lines = implode("\n", (new TrialEnding($subscription))->toMail($subscription->user)->introLines);

    expect($lines)->toContain($subscription->price->label())
        ->toContain(app(Settings::class)->formatDate(now()->addDays(3)));
});
