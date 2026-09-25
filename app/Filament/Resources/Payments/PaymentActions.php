<?php

namespace App\Filament\Resources\Payments;

use App\Filament\Forms\MoneyInput;
use App\Models\Payment;
use App\Models\User;
use App\Payments\Actions\CapturePayment;
use App\Payments\Actions\RefundPayment;
use App\Payments\Actions\VoidPayment;
use App\Payments\Enums\PaymentStatus;
use App\Payments\Enums\RefundStatus;
use App\Payments\Exceptions\GatewayException;
use App\Payments\Exceptions\PaymentNotAllowed;
use App\Payments\Money;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

/**
 * The money-moving actions on a payment, shared by the table and view page.
 *
 * Each modal mints an idempotency key when it opens, carried in a hidden
 * field, so submitting the same modal twice -- a double click, a retried
 * request -- refunds once. Every action authorizes against the payment's
 * policy and reports the gateway's own words when it refuses.
 */
class PaymentActions
{
    public static function refund(): Action
    {
        return Action::make('refund')
            ->label(__('payments.actions.refund'))
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('warning')
            // Decided from the row's own projections, not refundableMoney(), which
            // queries pending refunds: this runs once per table row. The exact
            // amount is checked under the row lock when the refund is made.
            ->visible(fn (Payment $record): bool => in_array($record->status, [PaymentStatus::Succeeded, PaymentStatus::PartiallyRefunded], true)
                && $record->amount_captured > $record->amount_refunded)
            ->authorize(fn (Payment $record): bool => auth()->user()?->can('refund', $record) ?? false)
            ->modalHeading(__('payments.actions.refund_heading'))
            ->modalSubmitActionLabel(__('payments.actions.refund_submit'))
            ->fillForm(fn (Payment $record): array => [
                'idempotency_key' => 'refund:'.Str::uuid(),
                'amount' => $record->refundableMoney()->amount,
            ])
            ->schema(fn (Payment $record): array => [
                Hidden::make('idempotency_key'),
                MoneyInput::make('amount', $record->currency)
                    ->label(__('payments.actions.refund_amount'))
                    ->helperText(__('payments.actions.refund_amount_help', ['amount' => $record->refundableMoney()->format()]))
                    ->required(),
                TextInput::make('reason')
                    ->label(__('payments.actions.refund_reason'))
                    ->maxLength(255),
            ])
            ->action(function (Payment $record, array $data): void {
                $user = auth()->user();

                try {
                    $refund = app(RefundPayment::class)->handle(
                        $record,
                        Money::of((int) $data['amount'], $record->currency),
                        (string) $data['idempotency_key'],
                        filled($data['reason'] ?? null) ? (string) $data['reason'] : null,
                        $user instanceof User ? $user : null,
                    );
                } catch (PaymentNotAllowed|GatewayException $e) {
                    Notification::make()->danger()->title(__('payments.actions.refund_failed'))->body($e->getMessage())->send();

                    return;
                }

                $refund->status === RefundStatus::Succeeded
                    ? Notification::make()->success()->title(__('payments.actions.refunded'))->send()
                    : Notification::make()->warning()->title(__('payments.actions.refund_pending'))->body(__('payments.actions.refund_pending_body'))->send();
            });
    }

    public static function capture(): Action
    {
        return Action::make('capture')
            ->label(__('payments.actions.capture'))
            ->icon(Heroicon::OutlinedBanknotes)
            ->color('success')
            ->visible(fn (Payment $record): bool => $record->status === PaymentStatus::Authorized)
            ->authorize(fn (Payment $record): bool => auth()->user()?->can('capture', $record) ?? false)
            ->modalHeading(__('payments.actions.capture_heading'))
            ->fillForm(fn (Payment $record): array => ['amount' => $record->amount])
            ->schema(fn (Payment $record): array => [
                MoneyInput::make('amount', $record->currency)
                    ->label(__('payments.actions.capture_amount'))
                    ->helperText(__('payments.actions.capture_amount_help', ['amount' => $record->total()->format()]))
                    ->required(),
            ])
            ->action(function (Payment $record, array $data): void {
                try {
                    app(CapturePayment::class)->handle($record, Money::of((int) $data['amount'], $record->currency));
                } catch (PaymentNotAllowed|GatewayException $e) {
                    Notification::make()->danger()->title(__('payments.actions.capture_failed'))->body($e->getMessage())->send();

                    return;
                }

                Notification::make()->success()->title(__('payments.actions.captured'))->send();
            });
    }

    public static function void(): Action
    {
        return Action::make('void')
            ->label(__('payments.actions.void'))
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->visible(fn (Payment $record): bool => $record->status === PaymentStatus::Authorized)
            ->authorize(fn (Payment $record): bool => auth()->user()?->can('void', $record) ?? false)
            ->requiresConfirmation()
            ->modalHeading(__('payments.actions.void_heading'))
            ->modalDescription(__('payments.actions.void_description'))
            ->action(function (Payment $record): void {
                try {
                    app(VoidPayment::class)->handle($record);
                } catch (PaymentNotAllowed|GatewayException $e) {
                    Notification::make()->danger()->title(__('payments.actions.void_failed'))->body($e->getMessage())->send();

                    return;
                }

                Notification::make()->success()->title(__('payments.actions.voided'))->send();
            });
    }
}
