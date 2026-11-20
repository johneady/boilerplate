<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use InstallerToolkit\Docs\Documentation as Guides;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * The app's guides (docs/*.md) and changelog, read inside the admin panel.
 *
 * A buyer has no other copy: package:build ships docs/ and CHANGELOG.md only
 * when package-config.php sets 'include_docs' => true, and readme.html then
 * points here. Rendering, heading ids and links between guides are handled by
 * InstallerToolkit\Docs\Documentation.
 *
 * Published from the toolkit's stub (vendor:publish --tag=docs-filament) and
 * adapted: admin panel navigation, translated labels, and this app's guides.
 * Any panel user may read them; nothing in them is secret.
 */
class Documentation extends Page
{
    /**
     * The guides in menu order: slug => [file relative to base_path(), label key].
     * Guides whose file is missing are left out of the menu.
     *
     * @var array<string, array{file: string, label: string}>
     */
    protected const GUIDES = [
        'overview' => ['file' => 'docs/README.md', 'label' => 'settings.documentation.guides.overview'],
        'installation-shared-hosting' => ['file' => 'docs/installation-shared-hosting.md', 'label' => 'settings.documentation.guides.installation'],
        'content' => ['file' => 'docs/content.md', 'label' => 'settings.documentation.guides.content'],
        'payments' => ['file' => 'docs/payments.md', 'label' => 'settings.documentation.guides.payments'],
        'users-and-roles' => ['file' => 'docs/users-and-roles.md', 'label' => 'settings.documentation.guides.users'],
        'changelog' => ['file' => 'CHANGELOG.md', 'label' => 'settings.documentation.guides.changelog'],
    ];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 99;

    protected string $view = 'filament.pages.documentation';

    /**
     * Untyped on purpose: it comes from the query string, and a typed
     * property would fail to hydrate ?guide[]=x instead of falling back to
     * the first guide.
     */
    #[Url]
    public mixed $guide = null;

    public static function getNavigationLabel(): string
    {
        return __('settings.documentation.title');
    }

    public function getTitle(): string
    {
        return __('settings.documentation.title');
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $guides = new Guides(array_map(fn (array $guide): string => $guide['file'], static::GUIDES), base_path());
        $current = $guides->resolve(is_string($this->guide) ? $this->guide : null);

        return [
            'guides' => array_map(
                fn (array $guide): string => __($guide['label']),
                array_intersect_key(static::GUIDES, $guides->available()),
            ),
            'current' => $current,
            'content' => $current === null
                ? null
                : new HtmlString($guides->render($current, fn (string $slug): string => static::getUrl(['guide' => $slug]))),
        ];
    }
}
