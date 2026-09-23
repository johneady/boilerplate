<?php

namespace App\Bakery;

/**
 * What an order is for. Optional on the form, and useful to the baker when
 * deciding how to decorate and box it.
 */
enum Occasion: string
{
    case Birthday = 'birthday';

    case Wedding = 'wedding';

    case Holiday = 'holiday';

    case Office = 'office';

    case JustBecause = 'just-because';

    case Other = 'other';

    /**
     * The label shown wherever an occasion is listed for a human.
     */
    public function label(): string
    {
        return match ($this) {
            self::Birthday => 'Birthday',
            self::Wedding => 'Wedding or shower',
            self::Holiday => 'Holiday gathering',
            self::Office => 'Office or event',
            self::JustBecause => 'Just because',
            self::Other => 'Something else',
        };
    }
}
