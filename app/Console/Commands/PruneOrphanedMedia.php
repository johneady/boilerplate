<?php

namespace App\Console\Commands;

use App\Models\Media;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

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

        // chunkById rather than chunk: the rows are being DELETED as we go, so
        // an offset-based chunk would skip every second page as the result set
        // shrinks underneath it.
        Media::query()
            ->orphaned()
            ->where('created_at', '<', $cutoff)
            ->chunkById(self::CHUNK_SIZE, function (Collection $media) use (&$deleted): void {
                /** @var Collection<int, Media> $media */
                foreach ($media as $item) {
                    $item->delete();
                    $deleted++;
                }
            });

        $this->info($deleted === 1
            ? 'Deleted 1 orphaned media file.'
            : "Deleted {$deleted} orphaned media files.");

        return self::SUCCESS;
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
