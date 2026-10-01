<?php

namespace App\Travel;

use App\Models\TourDeparture;
use App\Models\TripInquiry;
use Illuminate\Support\Facades\DB;

/**
 * Moves a booking request through its statuses, holding and releasing seats.
 *
 * Seats are held only by a CONFIRMED request, and the check and the hold happen
 * under a row lock on the departure, so two staff confirming the last seats at
 * the same moment cannot oversell it.
 */
class BookingWorkflow
{
    public function markContacted(TripInquiry $inquiry): void
    {
        if ($inquiry->status === InquiryStatus::New) {
            $inquiry->status = InquiryStatus::Contacted;
            $inquiry->save();
        }
    }

    /**
     * Confirm the request and hold its seats on the departure.
     *
     * @throws NotEnoughSeats
     */
    public function confirm(TripInquiry $inquiry): void
    {
        if ($inquiry->status === InquiryStatus::Confirmed) {
            return;
        }

        DB::transaction(function () use ($inquiry): void {
            if ($inquiry->tour_departure_id !== null) {
                $departure = TourDeparture::query()->lockForUpdate()->findOrFail($inquiry->tour_departure_id);

                if ($departure->seatsLeft() < $inquiry->travellers()) {
                    throw NotEnoughSeats::on($departure, $inquiry->travellers());
                }

                $departure->increment('seats_held', $inquiry->travellers());
            }

            $inquiry->status = InquiryStatus::Confirmed;
            $inquiry->confirmed_at = now();
            $inquiry->save();
        });
    }

    /**
     * Decline the request, giving back any seats it held.
     */
    public function decline(TripInquiry $inquiry): void
    {
        if ($inquiry->status === InquiryStatus::Declined) {
            return;
        }

        DB::transaction(function () use ($inquiry): void {
            if ($inquiry->status === InquiryStatus::Confirmed && $inquiry->tour_departure_id !== null) {
                $departure = TourDeparture::query()->lockForUpdate()->find($inquiry->tour_departure_id);

                $departure?->decrement('seats_held', min($departure->seats_held, $inquiry->travellers()));
            }

            $inquiry->status = InquiryStatus::Declined;
            $inquiry->confirmed_at = null;
            $inquiry->save();
        });
    }
}
