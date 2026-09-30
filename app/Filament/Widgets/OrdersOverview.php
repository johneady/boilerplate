<?php

namespace App\Filament\Widgets;

use App\Auth\Permission;
use App\Models\Order;
use App\Ordering\OrderStatus;
use App\Ordering\Price;
use App\Settings\SettingKey;
use App\Settings\Settings;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Today at the counter: how many orders came in, what they are worth, and
 * the average order over the last week.
 *
 * "Today" is the café's day (the Timezone setting), not the server's.
 */
class OrdersOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '30s';

    public static function canView(): bool
    {
        return auth()->user()?->hasPermission(Permission::ViewOrders) ?? false;
    }

    protected function getStats(): array
    {
        $settings = app(Settings::class);
        $today = now($settings->string(SettingKey::Timezone))->toDateString();

        $todays = Order::query()->whereBetween('created_at', [$settings->startOfBusinessDay($today), $settings->endOfBusinessDay($today)]);
        $count = (clone $todays)->count();
        $open = (clone $todays)->whereIn('status', [OrderStatus::New, OrderStatus::Preparing, OrderStatus::Ready])->count();
        $sales = (int) (clone $todays)->where('status', '!=', OrderStatus::Cancelled)->sum('total_cents');

        $week = Order::query()->where('created_at', '>=', now()->subDays(7))->where('status', '!=', OrderStatus::Cancelled);
        $weekCount = (clone $week)->count();
        $average = $weekCount > 0 ? intdiv((int) (clone $week)->sum('total_cents'), $weekCount) : 0;

        return [
            Stat::make(__('ordering.widgets.today_orders'), number_format($count))
                ->icon('heroicon-m-shopping-bag')
                ->description(__('ordering.widgets.today_orders_description', ['count' => $open]))
                ->color('info'),
            Stat::make(__('ordering.widgets.today_sales'), Price::format($sales))
                ->icon('heroicon-m-banknotes')
                ->description(__('ordering.widgets.today_sales_description')),
            Stat::make(__('ordering.widgets.average_order'), Price::format($average))
                ->icon('heroicon-m-receipt-percent')
                ->description(__('ordering.widgets.average_order_description', ['count' => $weekCount])),
        ];
    }
}
