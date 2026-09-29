<?php

namespace App\Perfumes\Enums;

/**
 * Who a fragrance is marketed to. Named for the marketing, not the wearer:
 * anyone can wear anything, and the browse filter says so.
 */
enum Audience: string
{
    case Feminine = 'feminine';
    case Masculine = 'masculine';
    case Unisex = 'unisex';

    public function label(): string
    {
        return match ($this) {
            self::Feminine => 'Feminine',
            self::Masculine => 'Masculine',
            self::Unisex => 'Unisex',
        };
    }

    /**
     * Resolve a value as it appears in a data file, accepting the everyday
     * words (women, men) that upstream sources tend to use.
     */
    public static function fromSource(string $value): ?self
    {
        return match (strtolower(trim($value))) {
            'feminine', 'women', 'female', 'for women' => self::Feminine,
            'masculine', 'men', 'male', 'for men' => self::Masculine,
            'unisex', 'shared', 'all' => self::Unisex,
            default => null,
        };
    }
}
