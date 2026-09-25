<?php

namespace App\Filament\Exports;

use App\Payments\Money;
use App\Settings\SettingKey;
use App\Settings\Settings;
use Carbon\CarbonInterface;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

/**
 * Shared shape for exports a bookkeeper opens in a spreadsheet.
 *
 * Dates are ISO (2026-09-25) in the business's timezone rather than the
 * display format chosen in Settings: a spreadsheet sorts and parses ISO
 * dates, and "25 Sep 2026" is text to it. Money is a plain decimal with the
 * currency in its own column, so the amounts sum.
 */
abstract class SpreadsheetExporter extends Exporter
{
    /**
     * A date in the business's timezone, or blank.
     */
    protected static function date(?CarbonInterface $date): string
    {
        return $date?->timezone(app(Settings::class)->string(SettingKey::Timezone))->format('Y-m-d') ?? '';
    }

    /**
     * A calendar date (a DATE column) as it was entered, or blank.
     *
     * Not moved into the business's timezone: it loads as midnight UTC, and
     * shifting it west of UTC would give the day before.
     */
    protected static function calendarDate(?CarbonInterface $date): string
    {
        return $date?->format('Y-m-d') ?? '';
    }

    /**
     * A date and time in the business's timezone, or blank.
     */
    protected static function dateTime(?CarbonInterface $date): string
    {
        return $date?->timezone(app(Settings::class)->string(SettingKey::Timezone))->format('Y-m-d H:i') ?? '';
    }

    /**
     * An amount as a decimal a spreadsheet can sum, e.g. "1250.00".
     */
    protected static function amount(Money $money): string
    {
        return $money->toDecimal();
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = trans_choice('exports.completed', $export->successful_rows, ['count' => number_format($export->successful_rows)]);

        if ($failed = $export->getFailedRowsCount()) {
            $body .= ' '.trans_choice('exports.failed', $failed, ['count' => number_format($failed)]);
        }

        return $body;
    }
}
