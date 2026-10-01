<?php

namespace App\Travel;

use App\Models\TourDeparture;
use RuntimeException;

/**
 * Thrown when a party is larger than the seats a departure has left.
 */
class NotEnoughSeats extends RuntimeException
{
    public static function on(TourDeparture $departure, int $requested): self
    {
        return new self(__('Only :left seats are left on this departure; the request is for :requested.', [
            'left' => $departure->seatsLeft(),
            'requested' => $requested,
        ]));
    }
}
