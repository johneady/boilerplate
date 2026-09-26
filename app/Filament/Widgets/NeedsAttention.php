<?php

namespace App\Filament\Widgets;

use App\Auth\Permission;
use App\Filament\Resources\ContactSubmissions\ContactSubmissionResource;
use App\Filament\Resources\Disputes\DisputeResource;
use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Resources\Subscriptions\SubscriptionResource;
use App\Payments\BusinessMetrics;
use App\Payments\Enums\DisputeStatus;
use App\Payments\Enums\PaymentStatus;
use App\Payments\Enums\SubscriptionStatus;
use App\Payments\PaymentManager;
use Filament\Widgets\Widget;

/**
 * What is waiting on someone: disputes with a deadline, renewals that failed,
 * holds about to lapse, messages nobody has answered.
 *
 * Each item appears only to someone allowed to act on it, and only while
 * there is something to do; with nothing outstanding the widget says so.
 */
class NeedsAttention extends Widget
{
    protected static ?int $sort = 2;

    protected string $view = 'filament.widgets.needs-attention';

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user !== null && (
            $user->hasPermission(Permission::ViewContactSubmissions)
            || ($user->hasPermission(Permission::ViewPayments) && app(PaymentManager::class)->enabled())
        );
    }

    /**
     * The outstanding items, each with how many and where to deal with them.
     *
     * @return list<array{label: string, count: int, url: string, icon: string, color: string}>
     */
    public function items(): array
    {
        $user = auth()->user();
        $metrics = app(BusinessMetrics::class);
        $items = [];

        if ($user?->hasPermission(Permission::ViewPayments) && app(PaymentManager::class)->enabled()) {
            $items[] = [
                'label' => 'dashboard.attention.disputes',
                'count' => $metrics->disputesNeedingResponse(),
                'url' => DisputeResource::getUrl('index', ['filters' => ['status' => ['values' => [DisputeStatus::NeedsResponse->value]]]]),
                'icon' => 'heroicon-o-shield-exclamation',
                'color' => 'danger',
            ];
            $items[] = [
                'label' => 'dashboard.attention.past_due',
                'count' => $metrics->pastDueSubscriptions(),
                'url' => SubscriptionResource::getUrl('index', ['filters' => ['status' => ['values' => [SubscriptionStatus::PastDue->value]]]]),
                'icon' => 'heroicon-o-credit-card',
                'color' => 'warning',
            ];
            $items[] = [
                'label' => 'dashboard.attention.holds',
                'count' => $metrics->holdsExpiringSoon(),
                'url' => PaymentResource::getUrl('index', ['filters' => ['status' => ['values' => [PaymentStatus::Authorized->value]]]]),
                'icon' => 'heroicon-o-clock',
                'color' => 'warning',
            ];
        }

        if ($user?->hasPermission(Permission::ViewContactSubmissions)) {
            $items[] = [
                'label' => 'dashboard.attention.messages',
                'count' => $metrics->unhandledMessages(),
                'url' => ContactSubmissionResource::getUrl('index', ['filters' => ['handled_at' => ['value' => '0']]]),
                'icon' => 'heroicon-o-envelope',
                'color' => 'info',
            ];
        }

        return array_values(array_map(
            fn (array $item): array => [...$item, 'label' => trans_choice($item['label'], $item['count'], ['count' => $item['count']])],
            array_filter($items, fn (array $item): bool => $item['count'] > 0),
        ));
    }
}
