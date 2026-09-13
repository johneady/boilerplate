---
paths:
  - app/Filament/Pages/Dashboard.php
---

# Pages

## The panel dashboard is a custom page, not Filament's base one
App\Filament\Pages\Dashboard replaces Filament\Pages\Dashboard as the panel's landing page. It renders resources/views/filament/pages/dashboard.blade.php — a business overview built from five widgets in app/Filament/Widgets (stats, two charts, two custom Blade widgets, all carrying sample data), laid out on a 3-column grid from getColumns(). The view must render `{{ $this->content }}` itself: page views receive the widget-grid schema through that expression, and nothing renders without it.

AdminPanelProvider must import App\Filament\Pages\Dashboard in pages(); re-running the panel generator reinstates Filament's own Dashboard import and silently reverts the page to an empty widget grid.

getWidgets() returns the widget classes explicitly, in display order — the panel also discovers them via discoverWidgets(), but discovery order is alphabetical, so the override exists to order the rows. Filament v5 embeds dashboard widgets as lazy-isolated Livewire components: their HTML is fetched client-side after load, so a feature test on the page response can only assert the widget wire:key names, never their content — test widget bodies with Livewire::test() on each class instead.

The introduction (the pitch adapted from the modal on johneady.duckdns.org) lives in an x-filament::modal with id work-overview. It opens by itself ten seconds after arrival but only once per visitor, gated on a work-overview-introduced flag in localStorage; after that the strip above the widgets is the way back in, dispatching open-modal from its "Show introduction" button. Closing uses close-modal with the same id.

tests/Feature/DashboardOverviewTest.php covers this. Note tests/Feature/DashboardTest.php is a different thing: the non-Filament /dashboard route.
