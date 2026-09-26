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
    use CachesWidgetMetrics;

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
     * The outstanding items the viewer may act on, each with how many and
     * where to deal with them.
     *
     * @return list<array{label: string, count: int, url: string, icon: string, color: string}>
     */
    public function items(): array
    {
        $user = auth()->user();
        $items = [];

        foreach ($this->attentionCounts() as $item => $count) {
            $definition = $this->definition($item);

            if (! ($user?->hasPermission($definition['permission']) ?? false)) {
                continue;
            }

            $items[] = [
                'label' => trans_choice("dashboard.attention.{$item}", $count, ['count' => $count]),
                'count' => $count,
                'url' => $definition['url'],
                'icon' => $definition['icon'],
                'color' => $definition['color'],
            ];
        }

        return $items;
    }

    /**
     * The outstanding counts behind the widget's short TTL.
     *
     * The per-viewer filtering happens above, outside the cache, so one warm
     * entry serves every permission level. The summary email calls
     * BusinessMetrics::attentionCounts() directly and so keeps reading
     * fresh figures.
     *
     * @return array<'disputes'|'past_due'|'holds'|'messages', int>
     */
    private function attentionCounts(): array
    {
        $payments = app(PaymentManager::class);

        return $this->rememberMetrics(
            'attention.'.$payments->mode()->value.'.payments-'.($payments->enabled() ? 'on' : 'off'),
            fn (): array => app(BusinessMetrics::class)->attentionCounts(),
        );
    }

    /**
     * Who may act on an item, and where it is dealt with.
     *
     * @param  'disputes'|'past_due'|'holds'|'messages'  $item
     * @return array{permission: Permission, url: string, icon: string, color: string}
     */
    private function definition(string $item): array
    {
        return match ($item) {
            'disputes' => [
                'permission' => Permission::ViewPayments,
                // The status filters take several values, so the link must
                // too, or it opens the whole unfiltered list.
                'url' => DisputeResource::getUrl('index', ['filters' => ['status' => ['values' => [DisputeStatus::NeedsResponse->value]]]]),
                'icon' => 'heroicon-o-shield-exclamation',
                'color' => 'danger',
            ],
            'past_due' => [
                'permission' => Permission::ViewPayments,
                'url' => SubscriptionResource::getUrl('index', ['filters' => ['status' => ['values' => [SubscriptionStatus::PastDue->value]]]]),
                'icon' => 'heroicon-o-credit-card',
                'color' => 'warning',
            ],
            'holds' => [
                'permission' => Permission::ViewPayments,
                'url' => PaymentResource::getUrl('index', ['filters' => ['status' => ['values' => [PaymentStatus::Authorized->value]]]]),
                'icon' => 'heroicon-o-clock',
                'color' => 'warning',
            ],
            'messages' => [
                'permission' => Permission::ViewContactSubmissions,
                'url' => ContactSubmissionResource::getUrl('index', ['filters' => ['handled_at' => ['value' => '0']]]),
                'icon' => 'heroicon-o-envelope',
                'color' => 'info',
            ],
        };
    }
}
