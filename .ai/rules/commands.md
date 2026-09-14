---
paths:
  - 'app/Jobs/ProcessUploadedImage.php, app/Console/Commands/PruneOrphanedMedia.php'
---

# Commands

## Deleting the media row is what cancels an in-flight upload
ProcessUploadedImage no longer takes userId/settingKey and there is no cache-marker guard. The row is written by MediaManager BEFORE dispatch, so the job looks its row up when it finishes: gone means the user hit Remove while it was queued, and the job deletes the conversions it just wrote rather than orphaning them on disk. The old removalKey()/settingRemovalKey() markers existed only because nothing represented a file until processing finished — there is now always a row to consult, and the guard no longer depends on CACHE_STORE being shared across processes.

app:prune-orphaned-media collects rows with no owner past config('media.orphan_retention_hours'). That window is a GRACE PERIOD, not a cleanup delay: a row is written before its owner exists (that is what lets a create form hold a file), so a fresh unattached row is indistinguishable from a form still open in someone's browser. Too short a window deletes the upload out from under the user.

The prune SPARES rows a page body still names, whatever their ownership: adopted page-body images are deliberately ownerless (see .ai/rules/queues-and-scheduling.md), and their "owner" is the body text referencing their URL, so a body naming the row's uuid directory means the file is in use. Once no body names it, the row is ordinary unowned media and is collected. The panel's orphan filter still lists spared rows -- a row with no owner record is worth seeing even while content keeps its files alive; do not "fix" that divergence by adding the body check to Media::scopeToOrphaned(), which is a SQL scope and cannot ask a per-row question without driver-specific SQL.

It also spares collections that are ownerless BY DESIGN (MediaCollection::isOwnerlessByDesign -- the Logo today): those rows belong to the installation, not to a record, and are not waiting for an owner the grace period could imagine. Before this spare existed, the hourly prune silently deleted the site logo ~24h after every upload.

The same run sweeps App\Media\StagedUpload::DIRECTORY past the same window: a staged source whose dispatch was lost, or whose job exhausted its retries, has no row pointing at it and nothing else would ever remove it. The window matters here too -- a queued job whose worker is behind may legitimately not have reached its source yet. Keep the window above the app:adopt-page-body-images daily cadence, or a not-yet-rewritten adoption loses its row mid-flight (see .ai/rules/queues-and-scheduling.md).

Prune deletes one model at a time via Media::delete(), never a query-builder delete — delete() is what removes the bytes. It uses chunkById, since rows vanish as it goes and an offset chunk would skip every second page.
