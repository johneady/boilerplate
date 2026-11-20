<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use InstallerToolkit\Update\RecentUpdateResults;
use UnitEnum;

/**
 * Launch page for installer-toolkit's standalone updater.
 *
 * The upload, validation, progress, extraction and rollback screens all live
 * in public/updater.php, a framework-free script shipped inside every .update
 * package. This page only shows the current version and recent outcomes, and
 * posts to the toolkit's launch route, which mints a one-time handoff token
 * and redirects there.
 *
 * Published from the toolkit's stub (vendor:publish) and adapted: admin panel
 * navigation, translated copy, and access limited to administrators -- the
 * same check the launch route itself makes (config('updates.authorize_upload')
 * is null, so it reads is_admin).
 */
class SystemUpdate extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPath;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 95;

    protected string $view = 'filament.pages.system-update';

    /**
     * Whether the current user may reach this page.
     *
     * Administrators only, not Permission::ManageSettings: an update replaces
     * the application's code, and the launch route refuses anyone without
     * is_admin, so showing the page to anyone else offers a button that 403s.
     * Hidden altogether where the updater is switched off (UPDATER_ENABLED),
     * since the toolkit then registers no launch route.
     */
    public static function canAccess(): bool
    {
        return config('updates.updater.enabled') && (bool) auth()->user()?->is_admin;
    }

    public static function getNavigationLabel(): string
    {
        return __('settings.system_update.title');
    }

    public function getTitle(): string
    {
        return __('settings.system_update.title');
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $results = app(RecentUpdateResults::class);
        $currentVersion = (string) config('app.version', '—');

        return [
            'currentVersion' => $currentVersion,
            'launchUrl' => route('updater.launch'),
            'results' => $results->take((int) config('updates.updater.recent_results', 5)),
            // The version first installed: what ran before the earliest
            // recorded update, or the current one when none is recorded.
            'installation' => $results->oldest()->versionFrom ?? $currentVersion,
        ];
    }
}
