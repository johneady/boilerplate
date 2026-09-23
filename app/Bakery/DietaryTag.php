<?php

namespace App\Bakery;

/**
 * The dietary and allergen notes a menu item can carry.
 *
 * Shown as small badges on the menu so a customer can scan for what they can
 * eat. "Contains nuts" is a warning, not a preference, which is why it has its
 * own colour. Labels are translated at the point of display.
 */
enum DietaryTag: string
{
    case Vegan = 'vegan';

    case GlutenFree = 'gluten-free';

    case DairyFree = 'dairy-free';

    case ContainsNuts = 'contains-nuts';

    /**
     * The label shown on the badge.
     */
    public function label(): string
    {
        return match ($this) {
            self::Vegan => 'Vegan',
            self::GlutenFree => 'Gluten-free',
            self::DairyFree => 'Dairy-free',
            self::ContainsNuts => 'Contains nuts',
        };
    }

    /**
     * The Flux badge colour on the public site.
     */
    public function color(): string
    {
        return match ($this) {
            self::Vegan, self::DairyFree => 'lime',
            self::GlutenFree => 'amber',
            self::ContainsNuts => 'rose',
        };
    }

    /**
     * The Filament badge colour in the admin panel.
     */
    public function panelColor(): string
    {
        return match ($this) {
            self::Vegan, self::DairyFree => 'success',
            self::GlutenFree => 'warning',
            self::ContainsNuts => 'danger',
        };
    }
}
