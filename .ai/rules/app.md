---
paths:
  - 'resources/views/layouts/app/**'
---

# App

## The signed-in shell is blue via accent tokens, not per-component overrides
The --color-accent/-content/-foreground tokens in resources/css/app.css are blue. Flux's sidebar.item resolves data-current:text-(--color-accent-content), and x-app-logo uses bg-accent-content, so changing those tokens themes the current nav item, brand mark and focus rings at once. Re-theme there rather than adding !important overrides per component.

Testing trap: asserting a tint class against the whole dashboard response is a false pass — the sidebar AND the mobile header both carry border-blue-100/bg-blue-50/70, so reverting only one still matches. Anchor the regex to the element (`/<ui-sidebar[^>]*\bbg-blue-50\/70\b/`).

Pest reuses compiled Blade in storage/framework/views. After editing a layout mid-run, `php artisan view:clear` or a stale template silently passes/fails the next run.

## The app layout is hardcoded dark — style the dark: variants or nothing changes
layouts/app/sidebar.blade.php and layouts/auth/split.blade.php set <html class="dark"> literally, with no light-mode path. The light utilities on the shell are therefore dead code in practice: only the dark: variants render.

Re-theming the shell and leaving dark:bg-zinc-* untouched looks completely unchanged in the browser while every test asserting the light class still passes. Change both variants, and assert the dark one (tests/Feature/DashboardTest.php does).

Verify against the running app, not just rendered HTML in a test: with public/hot present, CSS is served by the Vite dev server on :5173, not public/build, so `npm run build` alone proves nothing about what the browser gets. The dev server also emits unminified CSS with double-escaped selectors (.dark\\:bg-blue-950), so grepping for the single-escaped form gives a false MISSING.
