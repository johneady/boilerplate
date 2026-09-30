<?php

namespace App\Filament\Resources\Orders;

use App\Models\Order;
use App\Ordering\Fulfilment;
use App\Ordering\OrderStatus;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * The two things staff do to an order: move it to the next step, or cancel
 * it. Shared by the orders table, the order page and the dashboard queue.
 */
class OrderActions
{
    /**
     * "Start preparing" / "Mark ready" / "Mark collected", depending on where
     * the order is. Hidden once the order is finished.
     */
    public static function advance(): Action
    {
        return Action::make('advance')
            ->label(fn (Order $record): string => $record->status === OrderStatus::Ready && $record->fulfilment === Fulfilment::Delivery
                ? __('Mark delivered')
                : __((string) $record->status->advanceLabel()))
            ->icon(Heroicon::OutlinedArrowRightCircle)
            ->color('success')
            ->button()
            ->size('sm')
            ->visible(fn (Order $record): bool => $record->status->next() !== null)
            ->authorize(fn (Order $record): bool => auth()->user()?->can('update', $record) ?? false)
            ->action(function (Order $record): void {
                $record->advance();

                Notification::make()
                    ->success()
                    ->title(__('ordering.orders.advanced', ['number' => $record->number, 'status' => __($record->status->label())]))
                    ->send();
            });
    }

    public static function cancel(): Action
    {
        return Action::make('cancel')
            ->label(__('ordering.orders.actions.cancel'))
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription(__('ordering.orders.actions.cancel_confirm'))
            ->visible(fn (Order $record): bool => $record->status->canBeCancelled())
            ->authorize(fn (Order $record): bool => auth()->user()?->can('update', $record) ?? false)
            ->action(function (Order $record): void {
                $record->cancel();

                Notification::make()
                    ->success()
                    ->title(__('ordering.orders.cancelled', ['number' => $record->number]))
                    ->send();
            });
    }
}
