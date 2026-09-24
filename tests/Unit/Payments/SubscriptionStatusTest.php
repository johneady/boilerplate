<?php

use App\Payments\Enums\SubscriptionStatus;

test('an ended subscription can never come back', function (SubscriptionStatus $ended, SubscriptionStatus $to) {
    expect($ended->canTransitionTo($to))->toBeFalse();
})->with([
    'canceled to active' => [SubscriptionStatus::Canceled, SubscriptionStatus::Active],
    'canceled to past due' => [SubscriptionStatus::Canceled, SubscriptionStatus::PastDue],
    'expired to active' => [SubscriptionStatus::Expired, SubscriptionStatus::Active],
    'expired to trialing' => [SubscriptionStatus::Expired, SubscriptionStatus::Trialing],
]);

test('a started subscription cannot go back to being unfinished', function (SubscriptionStatus $from) {
    expect($from->canTransitionTo(SubscriptionStatus::Incomplete))->toBeFalse()
        ->and($from->canTransitionTo(SubscriptionStatus::Expired))->toBeFalse();
})->with([SubscriptionStatus::Trialing, SubscriptionStatus::Active, SubscriptionStatus::PastDue]);

test('a subscription may cycle between active and past due', function () {
    expect(SubscriptionStatus::Active->canTransitionTo(SubscriptionStatus::PastDue))->toBeTrue()
        ->and(SubscriptionStatus::PastDue->canTransitionTo(SubscriptionStatus::Active))->toBeTrue()
        ->and(SubscriptionStatus::Active->canTransitionTo(SubscriptionStatus::Trialing))->toBeFalse();
});

test('only a live subscription holds the user\'s slot', function (SubscriptionStatus $status, bool $live) {
    expect($status->isLive())->toBe($live);
})->with([
    [SubscriptionStatus::Incomplete, true],
    [SubscriptionStatus::Trialing, true],
    [SubscriptionStatus::Active, true],
    [SubscriptionStatus::PastDue, true],
    [SubscriptionStatus::Canceled, false],
    [SubscriptionStatus::Expired, false],
]);
