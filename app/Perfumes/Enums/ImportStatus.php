<?php

namespace App\Perfumes\Enums;

enum ImportStatus: string
{
    case Running = 'running';
    case Completed = 'completed';
    case CompletedWithErrors = 'completed_with_errors';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Running => 'Running',
            self::Completed => 'Completed',
            self::CompletedWithErrors => 'Completed with errors',
            self::Failed => 'Failed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Running => 'sky',
            self::Completed => 'success',
            self::CompletedWithErrors => 'warning',
            self::Failed => 'danger',
        };
    }
}
