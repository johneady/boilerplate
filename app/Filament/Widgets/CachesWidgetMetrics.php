<?php

namespace App\Filament\Widgets;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * A short TTL around the figures a dashboard widget renders.
 *
 * Deliberately here in the widgets and NOT in BusinessMetrics: the summary
 * email reads the same figures and must see fresh ones. The cache key
 * carries what the figures depend on -- the payments mode, the currency,
 * and the period being described -- never the `$now` bound, which changes
 * every second and would make an argument-keyed cache miss forever.
 *
 * TTL only, with no event-driven invalidation: month-level figures being up
 * to a minute stale is the trade, and a minute is nothing against the
 * alternative of re-running ~16 aggregates per dashboard load.
 */
trait CachesWidgetMetrics
{
    private const int METRICS_TTL_SECONDS = 60;

    /**
     * Read this widget's figures from the cache, computing them for the
     * next reader when missing.
     *
     * The closure must not capture $this: several cache stores serialize
     * the stored value, and a widget is a Livewire component.
     *
     * @template TFigures
     *
     * @param  Closure(): TFigures  $compute
     * @return TFigures
     */
    private function rememberMetrics(string $key, Closure $compute): mixed
    {
        return Cache::remember('dashboard.metrics.'.$key, self::METRICS_TTL_SECONDS, $compute);
    }
}
