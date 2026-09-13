---
paths:
  - 'app/Jobs/ProcessUploadedImage.php, app/Console/Commands/PruneOrphanedMedia.php'
---

# Commands

## Deleting the media row is what cancels an in-flight upload
ProcessUploadedImage no longer takes userId/settingKey and there is no cache-marker guard. The row is written by MediaManager BEFORE dispatch, so the job looks its row up when it finishes: gone means the user hit Remove while it was queued, and the job deletes the conversions it just wrote rather than orphaning them on disk. The old removalKey()/settingRemovalKey() markers existed only because nothing represented a file until processing finished — there is now always a row to consult, and the guard no longer depends on CACHE_STORE being shared across processes.

app:prune-orphaned-media collects rows with no owner past config('media.orphan_retention_hours'). That window is a GRACE PERIOD, not a cleanup delay: a row is written before its owner exists (that is what lets a create form hold a file), so a fresh unattached row is indistinguishable from a form still open in someone's browser. Too short a window deletes the upload out from under the user.

Prune deletes one model at a time via Media::delete(), never a query-builder delete — delete() is what removes the bytes. It uses chunkById, since rows vanish as it goes and an offset chunk would skip every second page.
