---
paths:
  - 'app/Models/Media.php, app/Filament/Resources/Media/**, app/Http/Controllers/MediaController.php'
---

# Controllers

## A media row outlives the model class it names
Never touch `$media->model` anywhere a failure would break a page. A row naming a model class that a later release renamed or removed makes Eloquent throw Error — not return null — the moment the relation resolves. Use `ownerOrNull()` (null for both a deleted record and a vanished class) or check `hasResolvableOwner()` first.

This bit twice from one cause: MediaResource eager-loaded `model`, so ONE stale row 500'd the entire library; and the orphaned scope matched only null columns, so the row was unreachable AND uncollectable — nothing pointed at it, nothing could, and the prune walked past it forever. The scope now also matches types whose class no longer exists (checked in PHP, since only the app knows what autoloads).

Filament filters receive a generic Builder, where a #[Scope] is invisible to PHPStan. Delegate to `Media::scopeToOrphaned($query)` rather than restating the condition inline — restating it is exactly how the panel's filter came to disagree with the prune, hiding rows the prune was about to delete.
