<?php

namespace App\Filament\Resources\Subscriptions;

use App\Models\Subscription;
use App\Payments\Actions\CancelSubscription;
use App\Payments\Actions\ReconcileSubscription;
use App\Payments\Actions\ResumeSubscription;
use App\Payments\Drivers\DemoDriver;
use App\Payments\Enums\Gateway;
use App\Payments\Enums\SubscriptionStatus;
use App\Payments\Enums\TransactionSource;
use App\Payments\Exceptions\GatewayException;
use App\Payments\Exceptions\PaymentNotAllowed;
use App\Payments\PaymentManager;
use Closure;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * The actions an operator can take on a subscription.
 *
 * Each goes to the gateway and then re-reads the subscription from it, so the
 * page shows what the gateway did. The Demo simulations stand in for time
 * passing: a renewal, or a renewal that fails, on demand.
 */
class SubscriptionActions
{
    public static function refresh(): Action
    {
        return Action::make('refresh')
            ->label(__('payments.subscriptions.refresh'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->authorize(fn (Subscription $record): bool => auth()->user()?->can('view', $record) ?? false)
            ->action(fn (Subscription $record) => self::run(
                fn () => app(ReconcileSubscription::class)->handle($record, TransactionSource::Admin),
                __('payments.subscriptions.refreshed'),
            ));
    }

    public static function cancelAtPeriodEnd(): Action
    {
        return Action::make('cancelAtPeriodEnd')
            ->label(__('payments.subscriptions.cancel_at_period_end_action'))
            ->icon(Heroicon::OutlinedCalendarDays)
            ->color('warning')
            ->visible(fn (Subscription $record): bool => self::isRunning($record) && ! $record->cancel_at_period_end)
            ->authorize(fn (Subscription $record): bool => auth()->user()?->can('manage', $record) ?? false)
            ->requiresConfirmation()
            ->modalDescription(__('payments.subscriptions.cancel_at_period_end_help'))
            ->action(fn (Subscription $record) => self::run(
                fn () => app(CancelSubscription::class)->handle($record, atPeriodEnd: true),
                __('payments.subscriptions.cancel_scheduled'),
            ));
    }

    public static function resume(): Action
    {
        return Action::make('resume')
            ->label(__('payments.subscriptions.resume'))
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('success')
            ->visible(fn (Subscription $record): bool => $record->isCancelScheduled())
            ->authorize(fn (Subscription $record): bool => auth()->user()?->can('manage', $record) ?? false)
            ->action(fn (Subscription $record) => self::run(
                fn () => app(ResumeSubscription::class)->handle($record),
                __('payments.subscriptions.resumed'),
            ));
    }

    public static function cancelNow(): Action
    {
        return Action::make('cancelNow')
            ->label(__('payments.subscriptions.cancel_now'))
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->visible(fn (Subscription $record): bool => self::isRunning($record))
            ->authorize(fn (Subscription $record): bool => auth()->user()?->can('manage', $record) ?? false)
            ->requiresConfirmation()
            ->modalDescription(__('payments.subscriptions.cancel_now_help'))
            ->action(fn (Subscription $record) => self::run(
                fn () => app(CancelSubscription::class)->handle($record, atPeriodEnd: false),
                __('payments.subscriptions.canceled'),
            ));
    }

    public static function simulateRenewal(): Action
    {
        return Action::make('simulateRenewal')
            ->label(__('payments.subscriptions.simulate_renewal'))
            ->icon(Heroicon::OutlinedForward)
            ->color('gray')
            ->visible(fn (Subscription $record): bool => $record->gateway === Gateway::Demo && self::isRunning($record) && ! $record->cancel_at_period_end)
            ->authorize(fn (Subscription $record): bool => auth()->user()?->can('manage', $record) ?? false)
            ->action(fn (Subscription $record) => self::run(function () use ($record): void {
                self::demoDriver($record)->simulateRenewal($record);
                app(ReconcileSubscription::class)->handle($record, TransactionSource::Demo);
            }, __('payments.subscriptions.renewed')));
    }

    public static function simulateFailedRenewal(): Action
    {
        return Action::make('simulateFailedRenewal')
            ->label(__('payments.subscriptions.simulate_failed_renewal'))
            ->icon(Heroicon::OutlinedExclamationTriangle)
            ->color('gray')
            ->visible(fn (Subscription $record): bool => $record->gateway === Gateway::Demo && self::isRunning($record))
            ->authorize(fn (Subscription $record): bool => auth()->user()?->can('manage', $record) ?? false)
            ->action(fn (Subscription $record) => self::run(function () use ($record): void {
                self::demoDriver($record)->simulateFailedRenewal($record);
                app(ReconcileSubscription::class)->handle($record, TransactionSource::Demo);
            }, __('payments.subscriptions.renewal_failed')));
    }

    private static function isRunning(Subscription $subscription): bool
    {
        return in_array($subscription->status, [SubscriptionStatus::Trialing, SubscriptionStatus::Active, SubscriptionStatus::PastDue], true);
    }

    private static function demoDriver(Subscription $subscription): DemoDriver
    {
        $driver = app(PaymentManager::class)->subscriptionDriver($subscription->gateway, $subscription->mode);
        assert($driver instanceof DemoDriver);

        return $driver;
    }

    /**
     * @param  Closure(): mixed  $action
     */
    private static function run(Closure $action, string $success): void
    {
        try {
            $action();
        } catch (PaymentNotAllowed|GatewayException $e) {
            Notification::make()->danger()->title(__('payments.subscriptions.action_failed'))->body($e->getMessage())->send();

            return;
        }

        Notification::make()->success()->title($success)->send();
    }
}
