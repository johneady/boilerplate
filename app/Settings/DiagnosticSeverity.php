<?php

namespace App\Settings;

/**
 * How seriously a failed production check should be taken.
 *
 * The split exists so the panel can distinguish "this is wrong in every
 * deployment" from "this may be deliberate here". A screen that painted every
 * finding red would train an administrator to ignore all of them.
 */
enum DiagnosticSeverity: string
{
    case Error = 'error';

    case Warning = 'warning';

    case Passed = 'passed';

    /**
     * The heading shown above this severity's group of findings.
     */
    public function label(): string
    {
        return match ($this) {
            self::Error => 'Errors',
            self::Warning => 'Warnings',
            self::Passed => 'Passed',
        };
    }

    /**
     * The Filament colour token used for this severity's badge and icon.
     */
    public function color(): string
    {
        return match ($this) {
            self::Error => 'danger',
            self::Warning => 'warning',
            self::Passed => 'success',
        };
    }
}
