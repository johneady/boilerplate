<?php

use App\Models\Enquiry;
use App\Notifications\EnquiryFollowUp;
use App\Voltiva\EnquiryStatus;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

/**
 * An enquiry whose first email went out when it arrived, as the form leaves it.
 */
function enquiryAfterFirstEmail(array $attributes = []): Enquiry
{
    $enquiry = Enquiry::factory()->create($attributes);
    $enquiry->recordFollowUpSent(1);

    return $enquiry;
}

test('the day 2 email is not sent before day 2', function () {
    Notification::fake();
    $this->travelTo('2026-09-01 10:00:00');
    enquiryAfterFirstEmail();

    $this->travelTo('2026-09-03 09:59:00');
    $this->artisan('app:send-enquiry-follow-ups')->assertSuccessful();

    Notification::assertNothingSent();
});

test('each email follows on its day, measured from the enquiry', function () {
    Notification::fake();
    $this->travelTo('2026-09-01 10:00:00');
    $enquiry = enquiryAfterFirstEmail();

    $this->travelTo('2026-09-03 10:00:00');
    $this->artisan('app:send-enquiry-follow-ups');

    Notification::assertSentOnDemand(EnquiryFollowUp::class, fn (EnquiryFollowUp $notification): bool => $notification->step === 2);
    expect($enquiry->refresh())
        ->follow_up_step->toBe(2)
        ->next_follow_up_at->toDateTimeString()->toBe('2026-09-06 10:00:00');
});

test('the sequence ends after the day 20 email', function () {
    Notification::fake();
    $this->travelTo('2026-09-01 10:00:00');
    $enquiry = enquiryAfterFirstEmail();

    foreach (['2026-09-03', '2026-09-06', '2026-09-11', '2026-09-21'] as $day) {
        $this->travelTo($day.' 10:00:00');
        $this->artisan('app:send-enquiry-follow-ups');
    }

    Notification::assertSentOnDemandTimes(EnquiryFollowUp::class, 4);
    expect($enquiry->refresh())
        ->follow_up_step->toBe(5)
        ->next_follow_up_at->toBeNull();
});

test('a closed enquiry receives no further emails', function (EnquiryStatus $status) {
    Notification::fake();
    $this->travelTo('2026-09-01 10:00:00');
    $enquiry = enquiryAfterFirstEmail();
    $enquiry->update(['status' => $status]);

    $this->travelTo('2026-09-03 10:00:00');
    $this->artisan('app:send-enquiry-follow-ups');

    Notification::assertNothingSent();
    expect($enquiry->refresh()->next_follow_up_at)->toBeNull();
})->with([EnquiryStatus::Won, EnquiryStatus::Lost]);

test('a first email that failed is retried by the scheduled run', function () {
    Notification::fake();
    $enquiry = Enquiry::factory()->create();

    $this->artisan('app:send-enquiry-follow-ups');

    Notification::assertSentOnDemand(EnquiryFollowUp::class, fn (EnquiryFollowUp $notification): bool => $notification->step === 1);
    expect($enquiry->refresh()->follow_up_step)->toBe(1);
});

test('every sequence email renders, with a link to stop them', function (int $step) {
    $enquiry = Enquiry::factory()->create(['name' => 'Marta']);

    $mail = (new EnquiryFollowUp($enquiry, $step))->toMail($enquiry);

    expect($mail->greeting)->toBe('Hello Marta,')
        ->and(implode(' ', $mail->outroLines))->toContain('/stop-emails?');
})->with([1, 2, 3, 4, 5]);

test('the stop link ends the sequence after the customer confirms', function () {
    $enquiry = enquiryAfterFirstEmail();
    $url = URL::signedRoute('enquiry.stop-emails', ['enquiry' => $enquiry]);

    $this->get($url)->assertSee('Stop the follow-up emails?');
    expect($enquiry->refresh()->next_follow_up_at)->not->toBeNull();

    $this->post($url)->assertSee('no more follow-up emails');
    expect($enquiry->refresh()->next_follow_up_at)->toBeNull();
});

test('the stop link must carry a valid signature', function () {
    $enquiry = enquiryAfterFirstEmail();

    $this->post(route('enquiry.stop-emails.confirm', $enquiry))->assertForbidden();

    expect($enquiry->refresh()->next_follow_up_at)->not->toBeNull();
});
