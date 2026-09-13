---
paths:
  - 'routes/web.php, app/Http/Controllers/PageController.php, app/Models/Page.php'
---

# Models

## The public page route is a fallback, not a catch-all
Content pages are served by `Route::fallback(PageController::class)`, NOT `Route::get('{page:slug}')`. Laravel matches in registration order, so an ordinary single-segment catch-all claims every path and shadows anything registered after it -- packages, and the routes ErrorPagesTest registers at runtime (that was a real 404-instead-of-403 failure). A fallback is tried only after every other route fails to match, whenever it was registered.

Two consequences that both fail silently if undone:
- A fallback route's parameter is named `fallbackPlaceholder`, so implicit binding cannot key on it. PageController resolves the slug itself.
- `route('pages.show', $page)` would build /1 from the primary key. `Page::getRouteKeyName()` returning 'slug' is what makes footer links point at /privacy. Without it every page link 404s.

`Page::RESERVED_SLUGS` blocks slugs a real route answers on -- such a page saves cleanly and is then permanently unreachable, since the fallback never sees the path. Re-check it with `php artisan route:list --except-vendor` after adding a public route. Covered by tests/Feature/PageTest.php and PageResourceTest.php.
