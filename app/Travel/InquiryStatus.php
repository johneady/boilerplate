<?php

namespace App\Travel;

/**
 * Where a booking request stands.
 *
 * New -> Contacted -> Confirmed, or Declined at any point before confirmation.
 * Only a confirmed request holds seats on its departure.
 */
enum InquiryStatus: string
{
    case New = 'new';

    case Contacted = 'contacted';

    case Confirmed = 'confirmed';

    case Declined = 'declined';

    /**
     * Plain English, translated at the point of display.
     */
    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Contacted => 'Contacted',
            self::Confirmed => 'Confirmed',
            self::Declined => 'Declined',
        };
    }

    /**
     * The badge colour in the admin panel and the customer dashboard.
     */
    public function color(): string
    {
        return match ($this) {
            self::New => 'warning',
            self::Contacted => 'info',
            self::Confirmed => 'success',
            self::Declined => 'gray',
        };
    }

    /**
     * Whether the request is still being worked on.
     */
    public function isOpen(): bool
    {
        return $this === self::New || $this === self::Contacted;
    }

    /**
     * Every case keyed by value, for a select field.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $status) {
            $options[$status->value] = __($status->label());
        }

        return $options;
    }
}
