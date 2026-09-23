<?php

use App\Models\User;
use App\Providers\Filament\AdminPanelProvider;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;

/**
 * Guards the cascade-layer order declared on every panel page. See
 * AdminPanelProvider::boot() for why it exists: without it, a plugin
 * stylesheet wrapped in @layer reorders the page's layers and Tailwind's
 * Preflight reset wipes the panel's layout, silently.
 */
test('panel pages declare the layer order before any stylesheet', function () {
    $html = $this->actingAs(User::factory()->admin()->create())
        ->get('/admin')
        ->assertSuccessful()
        ->getContent();

    $declaration = '<style>@layer properties, theme, base, components, utilities;</style>';
    $position = strpos($html, $declaration);

    expect($position)->not->toBeFalse()
        ->and($position)->toBeLessThan(strpos($html, 'rel="stylesheet"'))
        ->and(strpos($html, '@layer'))->toBe($position + strlen('<style>'));
});

test('no plugin stylesheet names a layer outside the declared order', function () {
    // A layer the declaration does not name is appended after `utilities`,
    // so it would outrank every Filament rule however the order is pinned.
    $undeclaredLayers = collect(FilamentAsset::getStyles())
        ->filter(fn (Css $style): bool => ! $style->isRemote() && is_file((string) $style->getPath()))
        ->flatMap(function (Css $style): array {
            preg_match_all('/@layer\s+([^{;]+)[{;]/', (string) file_get_contents($style->getPath()), $matches);

            return array_map(trim(...), explode(',', implode(',', $matches[1])));
        })
        ->filter()
        ->diff(AdminPanelProvider::CSS_LAYER_ORDER)
        ->unique()
        ->values()
        ->all();

    expect($undeclaredLayers)->toBe([]);
});
