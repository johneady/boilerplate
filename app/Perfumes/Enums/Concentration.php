<?php

namespace App\Perfumes\Enums;

/**
 * How concentrated a fragrance is, strongest first.
 *
 * Refresh files are hand-maintained, so fromSource() accepts the common
 * abbreviations (EDP, EDT) as well as the full names.
 */
enum Concentration: string
{
    case Extrait = 'extrait';
    case Parfum = 'parfum';
    case EauDeParfum = 'edp';
    case EauDeToilette = 'edt';
    case Cologne = 'edc';

    public function label(): string
    {
        return match ($this) {
            self::Extrait => 'Extrait de Parfum',
            self::Parfum => 'Parfum',
            self::EauDeParfum => 'Eau de Parfum',
            self::EauDeToilette => 'Eau de Toilette',
            self::Cologne => 'Eau de Cologne',
        };
    }

    /**
     * Resolve a value as it appears in a data file: the stored value, the
     * label, or a common abbreviation, in any case.
     */
    public static function fromSource(string $value): ?self
    {
        $value = strtolower(trim($value));

        foreach (self::cases() as $case) {
            if ($value === $case->value || $value === strtolower($case->label())) {
                return $case;
            }
        }

        return match ($value) {
            'extrait de parfum', 'extract' => self::Extrait,
            'eau de cologne', 'cologne' => self::Cologne,
            default => null,
        };
    }
}
