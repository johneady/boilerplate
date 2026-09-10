---
paths:
  - app/Filament/Pages/Dashboard.php
---

# Pages

## The panel dashboard is a custom page, not Filament's base one
App\Filament\Pages\Dashboard replaces Filament\Pages\Dashboard as the panel's landing page. It renders resources/views/filament/pages/dashboard.blade.php — an overview of the work John Eady does, adapted from the introduction modal on johneady.duckdns.org (inline rather than a popup, since a dashboard is already the first thing an admin sees).

AdminPanelProvider must import App\Filament\Pages\Dashboard in pages(); re-running the panel generator reinstates Filament's own Dashboard import and silently reverts the page to an empty widget grid.

getWidgets() returns [] on purpose — the panel registers no widgets, so the inherited grid would only render an empty container above the content. Remove that override when real widgets are added.

tests/Feature/DashboardOverviewTest.php covers this. Note tests/Feature/DashboardTest.php is a different thing: the non-Filament /dashboard route.
