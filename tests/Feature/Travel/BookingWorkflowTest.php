<?php

use App\Models\TourDeparture;
use App\Models\TripInquiry;
use App\Travel\BookingWorkflow;
use App\Travel\InquiryStatus;
use App\Travel\NotEnoughSeats;

test('confirming a request holds its seats on the departure', function () {
    $departure = TourDeparture::factory()->seatsLeft(6)->create();
    $inquiry = TripInquiry::factory()->forDeparture($departure)->create(['adults' => 2, 'children' => 1]);

    app(BookingWorkflow::class)->confirm($inquiry);

    expect($inquiry->fresh()->status)->toBe(InquiryStatus::Confirmed)
        ->and($inquiry->fresh()->confirmed_at)->not->toBeNull()
        ->and($departure->fresh()->seatsLeft())->toBe(3);
});

test('confirming twice holds the seats once', function () {
    $departure = TourDeparture::factory()->seatsLeft(6)->create();
    $inquiry = TripInquiry::factory()->forDeparture($departure)->create(['adults' => 2]);

    app(BookingWorkflow::class)->confirm($inquiry);
    app(BookingWorkflow::class)->confirm($inquiry->fresh());

    expect($departure->fresh()->seatsLeft())->toBe(4);
});

test('a request larger than the seats left cannot be confirmed', function () {
    $departure = TourDeparture::factory()->seatsLeft(1)->create();
    $inquiry = TripInquiry::factory()->forDeparture($departure)->create(['adults' => 2]);

    expect(fn () => app(BookingWorkflow::class)->confirm($inquiry))->toThrow(NotEnoughSeats::class);

    expect($inquiry->fresh()->status)->toBe(InquiryStatus::New)
        ->and($departure->fresh()->seatsLeft())->toBe(1);
});

test('declining a confirmed request releases its seats', function () {
    $departure = TourDeparture::factory()->seatsLeft(6)->create();
    $inquiry = TripInquiry::factory()->forDeparture($departure)->create(['adults' => 2]);
    app(BookingWorkflow::class)->confirm($inquiry);

    app(BookingWorkflow::class)->decline($inquiry->fresh());

    expect($inquiry->fresh()->status)->toBe(InquiryStatus::Declined)
        ->and($departure->fresh()->seatsLeft())->toBe(6);
});

test('declining an unconfirmed request leaves the departure alone', function () {
    $departure = TourDeparture::factory()->seatsLeft(6)->create();
    $inquiry = TripInquiry::factory()->forDeparture($departure)->create(['adults' => 2]);

    app(BookingWorkflow::class)->decline($inquiry);

    expect($departure->fresh()->seatsLeft())->toBe(6);
});

test('a tailor-made request can be confirmed without a departure', function () {
    $inquiry = TripInquiry::factory()->create();

    app(BookingWorkflow::class)->confirm($inquiry);

    expect($inquiry->fresh()->status)->toBe(InquiryStatus::Confirmed);
});
