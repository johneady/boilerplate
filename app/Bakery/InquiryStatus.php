<?php

namespace App\Bakery;

/**
 * Where an order inquiry is between arriving and being collected.
 *
 * An inquiry is a request, not a purchase: the baker checks the date against
 * their kitchen, replies with a price, and only a Confirmed inquiry is on the
 * baking schedule.
 */
enum InquiryStatus: string
{
    case New = 'new';

    case Quoted = 'quoted';

    case Confirmed = 'confirmed';

    case Completed = 'completed';

    case Declined = 'declined';

    /**
     * The label shown in the admin panel.
     */
    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Quoted => 'Quote sent',
            self::Confirmed => 'Confirmed',
            self::Completed => 'Collected / delivered',
            self::Declined => 'Declined',
        };
    }

    /**
     * The Filament colour of the status badge.
     */
    public function color(): string
    {
        return match ($this) {
            self::New => 'warning',
            self::Quoted => 'info',
            self::Confirmed => 'primary',
            self::Completed => 'success',
            self::Declined => 'gray',
        };
    }

    /**
     * Whether the order still needs the baker's attention.
     */
    public function isOpen(): bool
    {
        return in_array($this, [self::New, self::Quoted, self::Confirmed], true);
    }
}
