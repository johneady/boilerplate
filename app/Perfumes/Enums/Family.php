<?php

namespace App\Perfumes\Enums;

/**
 * The olfactory family a fragrance belongs to, after the families used by
 * the Société Française des Parfumeurs' classification, simplified to the
 * ones a shopper recognises.
 */
enum Family: string
{
    case Floral = 'floral';
    case Amber = 'amber';
    case Woody = 'woody';
    case Fresh = 'fresh';
    case Citrus = 'citrus';
    case Chypre = 'chypre';
    case Fougere = 'fougere';
    case Gourmand = 'gourmand';
    case Leather = 'leather';
    case Aromatic = 'aromatic';

    public function label(): string
    {
        return match ($this) {
            self::Floral => 'Floral',
            self::Amber => 'Amber',
            self::Woody => 'Woody',
            self::Fresh => 'Fresh',
            self::Citrus => 'Citrus',
            self::Chypre => 'Chypre',
            self::Fougere => 'Fougère',
            self::Gourmand => 'Gourmand',
            self::Leather => 'Leather',
            self::Aromatic => 'Aromatic',
        };
    }

    /**
     * A swatch for badges and cards. Plain hex rather than Tailwind classes,
     * so it survives being defined outside resources/ (Tailwind's @source).
     */
    public function color(): string
    {
        return match ($this) {
            self::Floral => '#e11d74',
            self::Amber => '#c2410c',
            self::Woody => '#78543a',
            self::Fresh => '#0891b2',
            self::Citrus => '#ca8a04',
            self::Chypre => '#4d7c0f',
            self::Fougere => '#15803d',
            self::Gourmand => '#a16207',
            self::Leather => '#57331f',
            self::Aromatic => '#6d28d9',
        };
    }

    /**
     * Resolve a value as it appears in a data file. "Oriental" is the older
     * name for the amber family and still common in exported data.
     */
    public static function fromSource(string $value): ?self
    {
        $value = strtolower(trim($value));

        foreach (self::cases() as $case) {
            if ($value === $case->value || $value === mb_strtolower($case->label())) {
                return $case;
            }
        }

        return match ($value) {
            'oriental' => self::Amber,
            'fruity', 'aquatic', 'green' => self::Fresh,
            default => null,
        };
    }
}
