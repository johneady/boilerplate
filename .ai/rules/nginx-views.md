---
paths:
  - 'app/Http/Controllers/RobotsController.php, app/Http/Controllers/SitemapController.php, docker/nginx/default.conf, resources/views/sitemap.blade.php'
---

# Nginx Views

## robots.txt and sitemap.xml are rendered by PHP, not files in public/
Both follow SettingKey::AllowSearchIndexing, so neither can be a static file. public/robots.txt was deleted on purpose — a real file in public/ is matched by the web server before the request reaches PHP, which would silently restore the old mismatch (meta robots said noindex while robots.txt still invited crawlers). A test asserts public/robots.txt does not exist.

docker/nginx/default.conf needs try_files on BOTH exact-match locations. The old `location = /robots.txt { access_log off; log_not_found off; }` had no try_files, so it 404s in production while `artisan serve` still works — a difference only production shows. Same trap applies to any new app-rendered path that looks like a static file.

Indexing off serves `Disallow: /` and an EMPTY sitemap, not a 404: a 404 leaves a previously submitted sitemap erroring in Search Console.

SitemapController::ROUTES is an explicit named-route list, not a sweep of the route table — most routes are auth-gated, POST targets, or the dev-only previews, and a crawler must see none of them. New public pages get added there.

resources/views/sitemap.blade.php echoes the XML declaration via `<?php echo ... ?>` because Blade would hand a literal `<?xml` to PHP's short-open-tag parsing.
