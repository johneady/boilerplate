---
paths:
  - 'app/Providers/AuthServiceProvider.php, app/Policies/MediaPolicy.php, app/Filament/Resources/Media/**'
---

# Media

## A policy denial does not reach an admin until Gate::before stands down
There are now THREE lists in AuthServiceProvider and they are not interchangeable:

- SELF_PROTECTED_ABILITIES — checks against the acting user's OWN account (delete/forceDelete/updateRole).
- IMMUTABLE_RECORD_ABILITIES — AuditLog: every write ability including delete, because nobody may prune the trail by hand.
- CODE_WRITTEN_RECORD_ABILITIES — Media: create/update/replicate only. Delete stays allowed, because deleting IS how a file is removed and Media::delete() takes the bytes with it.

Writing `'create' => null` in a policy (or omitting it, which BasePolicy denies by default) does NOT deny an administrator — Gate::before short-circuits before the policy is ever consulted, so the resource renders a "New" button that then fails. MediaResource::canCreate() returning true was exactly this. Filament asks the CLASS form (`can('create', Media::class)`) with no model, so any new list must match on the class name too, not only an instance.

MediaResource has no create/edit page on purpose: uploads go through MediaManager (validate → stage privately → re-encode), and a row is a statement about bytes that editing cannot move.
