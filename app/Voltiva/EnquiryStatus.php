<?php

namespace App\Voltiva;

/**
 * Where an enquiry is in the sales team's follow-up.
 *
 * Closing an enquiry -- won or lost -- is also what stops its automatic
 * email sequence: nobody who has just bought a car, or said no, should get
 * "still thinking about it?" a week later.
 */
enum EnquiryStatus: string
{
    case New = 'new';

    case Contacted = 'contacted';

    case TestDrive = 'test_drive';

    case Quoted = 'quoted';

    case Won = 'won';

    case Lost = 'lost';

    /**
     * The label shown in the admin panel.
     */
    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Contacted => 'Contacted',
            self::TestDrive => 'Test drive booked',
            self::Quoted => 'Quote sent',
            self::Won => 'Sold',
            self::Lost => 'Closed – not proceeding',
        };
    }

    /**
     * The Filament colour of the status badge.
     */
    public function color(): string
    {
        return match ($this) {
            self::New => 'warning',
            self::Contacted => 'info',
            self::TestDrive, self::Quoted => 'primary',
            self::Won => 'success',
            self::Lost => 'gray',
        };
    }

    /**
     * Whether the automatic email sequence keeps running at this status.
     */
    public function receivesFollowUps(): bool
    {
        return ! in_array($this, [self::Won, self::Lost], true);
    }
}
