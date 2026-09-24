<?php

namespace App\Filament\Actions;

use App\Auth\Permission;
use App\Filament\Forms\MoneyInput;
use App\Models\User;
use App\Payments\Actions\RecordManualPayment;
use App\Payments\Contracts\Payable;
use App\Payments\Enums\ManualPaymentMethod;
use App\Payments\Exceptions\InvalidPaymentAmount;
use App\Payments\Exceptions\PaymentNotAllowed;
use App\Payments\Money;
use App\Payments\PaymentManager;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Record money received outside the site against any payable.
 *
 * Reusable on any resource whose records implement Payable -- the payment
 * links today, a project's own Order or Invoice tomorrow -- so recording an
 * e-Transfer or a cheque is one line in that resource's table.
 */
class RecordManualPaymentAction
{
    public static function make(): Action
    {
        return Action::make('recordManualPayment')
            ->label(__('payments.actions.record_manual'))
            ->icon(Heroicon::OutlinedBanknotes)
            ->color('gray')
            ->visible(fn (Model $record): bool => $record instanceof Payable
                && $record->acceptsPayments()
                && app(PaymentManager::class)->manualPaymentsEnabled())
            ->authorize(fn (): bool => auth()->user()?->hasPermission(Permission::RecordManualPayments) ?? false)
            ->modalHeading(__('payments.actions.record_manual_heading'))
            ->fillForm(fn (Model $record): array => [
                'idempotency_key' => 'manual:'.Str::uuid(),
                'amount' => $record instanceof Payable ? self::suggestedAmount($record) : null,
                'method' => ManualPaymentMethod::ETransfer->value,
                'received_on' => now()->toDateString(),
            ])
            ->schema(fn (Model $record): array => [
                Hidden::make('idempotency_key'),
                MoneyInput::make('amount', $record instanceof Payable ? $record->paymentCurrency() : app(PaymentManager::class)->currency())
                    ->label(__('payments.actions.record_manual_amount'))
                    ->helperText(__('payments.actions.record_manual_amount_help'))
                    ->required(),
                Select::make('method')
                    ->label(__('payments.fields.manual_method'))
                    ->options(ManualPaymentMethod::options())
                    ->required()
                    ->selectablePlaceholder(false),
                TextInput::make('reference')
                    ->label(__('payments.fields.manual_reference'))
                    ->maxLength(255),
                DatePicker::make('received_on')
                    ->label(__('payments.fields.manual_received_on'))
                    ->maxDate(now())
                    ->required(),
                TextInput::make('customer_name')
                    ->label(__('payments.fields.customer_name'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('customer_email')
                    ->label(__('payments.fields.customer_email'))
                    ->email()
                    ->required()
                    ->maxLength(255),
            ])
            ->action(function (Model $record, array $data): void {
                $user = auth()->user();

                if (! $record instanceof Payable || ! $user instanceof User) {
                    return;
                }

                try {
                    app(RecordManualPayment::class)->handle(
                        payable: $record,
                        subtotal: Money::of((int) $data['amount'], $record->paymentCurrency()),
                        method: ManualPaymentMethod::from((string) $data['method']),
                        reference: filled($data['reference'] ?? null) ? (string) $data['reference'] : null,
                        receivedOn: CarbonImmutable::parse((string) $data['received_on']),
                        customerName: (string) $data['customer_name'],
                        customerEmail: (string) $data['customer_email'],
                        idempotencyKey: (string) $data['idempotency_key'],
                        recorder: $user,
                    );
                } catch (PaymentNotAllowed $e) {
                    Notification::make()->danger()->title(__('payments.actions.record_failed'))->body($e->getMessage())->send();

                    return;
                }

                Notification::make()->success()->title(__('payments.actions.recorded'))->send();
            });
    }

    /**
     * The payable's own price, when it has one. A payable that lets the
     * customer choose has nothing to suggest and throws when asked.
     */
    private static function suggestedAmount(Payable $payable): ?int
    {
        try {
            return $payable->amountDue()->amount;
        } catch (InvalidPaymentAmount) {
            return null;
        }
    }
}
