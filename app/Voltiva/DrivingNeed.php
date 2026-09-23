<?php

namespace App\Voltiva;

/**
 * The kinds of driving a customer ticks on the enquiry form.
 *
 * A fixed list rather than free text, so the sales team can filter enquiries
 * by it and the car finder can reason about it.
 */
enum DrivingNeed: string
{
    case Town = 'town';

    case Commute = 'commute';

    case CoastAndMountain = 'coast_mountain';

    case IslandTrips = 'island_trips';

    case Business = 'business';

    /**
     * The label shown beside the checkbox.
     */
    public function label(): string
    {
        return match ($this) {
            self::Town => 'Short trips around town',
            self::Commute => 'A daily commute',
            self::CoastAndMountain => 'Coast and mountain roads',
            self::IslandTrips => 'Longer trips across the island',
            self::Business => 'Business or deliveries',
        };
    }
}
