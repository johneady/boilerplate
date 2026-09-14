<?php

namespace App\Console\Commands;

use App\Media\MediaCollection;
use App\Media\StagedUpload;
use App\Models\Media;
use App\Models\Page;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Delete media rows that belong to nothing, along with their files.
 *
 * Two ways a row ends up orphaned, and the grace period is for the first:
 *
 * - A file uploaded against a form that was never submitted. The row is written
 *   before the owner exists (that is what lets a create form hold a file), so a
 *   row with no owner is INDISTINGUISHABLE from one whose form is still open in
 *   somebody's browser. Deleting those immediately would delete the upload out
 *   from under a user still filling the form in.
 * - A row whose owner was deleted through a path that did not run the model
 *   events -- a raw query, a truncate, a cascade at the database level.
 *
 * Deleted one model at a time rather than with a mass delete, because
 * Media::delete() is what removes the bytes; a query-builder delete would drop
 * the rows and leave every file behind with nothing left pointing at it.
 *
 * The same run collects abandoned STAGED uploads (see App\Media\StagedUpload):
 * a source whose dispatch was lost, or whose job exhausted its retries, has no
 * row pointing at it, so nothing else would ever remove it.
 */
class PruneOrphanedMedia extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:prune-orphaned-media {--hours= : How long an unattached file is kept}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete media rows with no owning record, and their files';

    /**
     * How many rows are loaded at once.
     */
    private const int CHUNK_SIZE = 100;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $hours = $this->retentionHours();

        if ($hours <= 0) {
            $this->info('Orphaned media pruning is disabled.');

            return self::SUCCESS;
        }

        $cutoff = now()->subHours($hours);

        $deleted = 0;
        $spared = 0;

        // chunkById rather than chunk: the rows are being DELETED as we go, so
        // an offset-based chunk would skip every second page as the result set
        // shrinks underneath it.
        Media::query()
            ->orphaned()
            ->where('created_at', '<', $cutoff)
            ->chunkById(self::CHUNK_SIZE, function (Collection $media) use (&$deleted, &$spared): void {
                /** @var Collection<int, Media> $media */
                foreach ($media as $item) {
                    // Some rows are ownerless BY DESIGN: the logo belongs to
                    // the installation, not to any record, and would otherwise
                    // be collected ~24h after it was uploaded. That is not a
                    // grace-period case -- nothing is ever going to attach it.
                    if (MediaCollection::tryFrom($item->collection)?->isOwnerlessByDesign()) {
                        $spared++;

                        continue;
                    }

                    // A body-referenced row is not an orphan, however it is
                    // owned. This is what keeps page-body adoptions alive
                    // after app:adopt-page-body-images made their rows
                    // ownerless (their "owner" is the body text naming them),
                    // and it holds for any row whose URL somebody pasted into
                    // content: the file is in use.
                    if ($this->isReferencedByPageBody($item)) {
                        $spared++;

                        continue;
                    }

                    $item->delete();
                    $deleted++;
                }
            });

        $this->info($deleted === 1
            ? 'Deleted 1 orphaned media file.'
            : "Deleted {$deleted} orphaned media files.");

        if ($spared > 0) {
            $this->line("Spared {$spared} ownerless-by-design or still referenced by a page body.");
        }

        $this->pruneStagedUploads($cutoff);

        return self::SUCCESS;
    }

    /**
     * Whether any page body still names this row's files.
     *
     * Matched on the row's stored directory: a uuid segment nothing else
     * writes into, so a containing body is referencing THIS row and not a
     * lookalike. Checked in PHP rather than the orphaned() scope because it is
     * a per-row question, and the panel's orphan filter deliberately keeps
     * listing these -- a row with no owner record is worth seeing even while
     * content keeps its files alive.
     */
    private function isReferencedByPageBody(Media $media): bool
    {
        return Page::query()
            ->where('body', 'like', '%'.$media->path.'%')
            ->exists();
    }

    /**
     * Delete staged uploads older than the retention window.
     *
     * A staged source exists only between staging and the job running; the
     * window is the same grace the rows get, and for the same reason -- a
     * queued job whose worker is behind may legitimately not have reached it
     * yet. Past the window the source is abandoned: its dispatch was lost, or
     * its job failed, and nothing else ever reads this directory.
     */
    private function pruneStagedUploads(CarbonInterface $cutoff): void
    {
        $disk = Storage::disk('local');

        $files = $disk->files(StagedUpload::DIRECTORY);

        if ($files === []) {
            return;
        }

        $deleted = 0;

        foreach ($files as $path) {
            if ($disk->lastModified($path) > $cutoff->getTimestamp()) {
                continue;
            }

            $disk->delete($path);

            $deleted++;
        }

        if ($deleted > 0) {
            $this->info($deleted === 1
                ? 'Deleted 1 abandoned staged upload.'
                : "Deleted {$deleted} abandoned staged uploads.");
        }
    }

    /**
     * How long an unattached file is kept before it is collected.
     *
     * The option wins over the configured value so an operator can run a
     * one-off sweep without editing config. A non-numeric or zero value means
     * keep everything, which is how pruning is turned off.
     */
    private function retentionHours(): int
    {
        $option = $this->option('hours');

        if ($option === null || $option === '') {
            return (int) config('media.orphan_retention_hours', 24);
        }

        return (int) $option;
    }
}
