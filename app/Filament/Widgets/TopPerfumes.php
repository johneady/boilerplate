<?php

namespace App\Filament\Widgets;

use App\Auth\Permission;
use App\Models\Perfume;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Where the attention is: the most viewed perfumes over the last 30 days,
 * with their follower counts beside them.
 */
class TopPerfumes extends TableWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 2;

    public static function canView(): bool
    {
        return auth()->user()?->hasPermission(Permission::ManagePerfumes) ?? false;
    }

    public function table(Table $table): Table
    {
        $since = now()->toImmutable()->startOfDay()->subDays(29)->toDateString();

        return $table
            ->heading(__('dashboard.top_perfumes.heading'))
            ->description(__('dashboard.top_perfumes.description'))
            ->query(fn (): Builder => Perfume::query()
                ->with('brand')
                ->withCount('followers')
                ->withSum(['dailyViews as period_views' => fn (Builder $query) => $query->where('viewed_on', '>=', $since)], 'views'))
            ->defaultSort('period_views', 'desc')
            ->paginated([5])
            ->defaultPaginationPageOption(5)
            ->columns([
                TextColumn::make('name')
                    ->label(__('perfumes.perfumes.fields.name'))
                    ->description(fn (Perfume $record): string => $record->brand->name)
                    ->url(fn (Perfume $record): string => route('perfumes.show', $record), shouldOpenInNewTab: true),
                TextColumn::make('period_views')
                    ->label(__('dashboard.top_perfumes.views'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('followers_count')
                    ->label(__('perfumes.perfumes.fields.followers'))
                    ->numeric()
                    ->sortable(),
            ]);
    }
}
