<?php

namespace App\Filament\Exports;

use App\Auth\Role;
use App\Models\User;
use Filament\Actions\Exports\ExportColumn;

/**
 * The customer list: who signed up, when, and what they subscribe to.
 */
class UserExporter extends SpreadsheetExporter
{
    protected static ?string $model = User::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('name')
                ->label(__('exports.users.name'))
                ->preventFormulaInjection(),
            ExportColumn::make('email')
                ->label(__('exports.users.email'))
                ->preventFormulaInjection(),
            ExportColumn::make('role')
                ->label(__('exports.users.role'))
                ->formatStateUsing(fn (Role $state): string => __($state->label())),
            ExportColumn::make('email_verified_at')
                ->label(__('exports.users.verified'))
                ->state(fn (User $record): string => $record->email_verified_at !== null ? __('exports.yes') : __('exports.no')),
            ExportColumn::make('created_at')
                ->label(__('exports.users.registered'))
                ->state(fn (User $record): string => static::date($record->created_at)),
            // One column rather than plan and status apart: finding the
            // current subscription is a query per user, so it runs once.
            ExportColumn::make('subscription')
                ->label(__('exports.users.subscription'))
                ->state(function (User $record): string {
                    $subscription = $record->currentSubscription();

                    return $subscription === null ? '' : __('exports.users.subscription_state', [
                        'plan' => $subscription->plan->name ?? '',
                        'status' => __($subscription->status->label()),
                    ]);
                }),
        ];
    }
}
