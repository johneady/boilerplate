<?php

namespace App\Console\Commands;

use App\Media\MediaCollection;
use App\Media\MediaManager;
use App\Media\StagedUpload;
use App\Models\Media;
use App\Models\Page;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Bring page-body image uploads under the media library, or collect them.
 *
 * The body editor uploads straight to the public disk and writes no media row
 * -- a deliberate trade so an author can add an image while writing (see
 * .ai/rules/filament-resources-pages.md). The cost is that those files are
 * invisible to everything else: not re-encoded, so EXIF survives into a public
 * URL, and untracked, so app:prune-orphaned-media cannot collect one after the
 * body stops referencing it. This command closes both gaps after the fact.
 *
 * Two outcomes, decided by whether any page body still points at the file:
 *
 * - REFERENCED: re-encoded through App\Media\MediaManager, given a media row,
 *   and every body URL rewritten to the processed one. The original is deleted
 *   only after the rewrite has committed, so a failure leaves the page pointing
 *   at a file that still exists.
 * - UNREFERENCED past the grace period: deleted, exactly as an orphaned media
 *   row would be. Adopting these would fill the library with abandoned test
 *   uploads, which makes it useless for seeing what is actually in use.
 *
 * Adoption spans two runs when the worker is busy: the first stages the file
 * and queues the job, and whichever later run finds the conversions written
 * does the rewrite. The row is what carries that state between runs, so a file
 * is adopted once however many times this command runs.
 *
 * Scoped to the page-body directory alone. A wider sweep would have to decide
 * what every other file on the disk is for, and the directories it would walk
 * are precisely the ones media rows already account for.
 */
class AdoptPageBodyImages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:adopt-page-body-images
        {--hours= : How long an unreferenced file is kept}
        {--dry-run : Report what would happen without changing anything}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Re-encode referenced page-body uploads into the media library, and collect the rest';

    /**
     * The directory the body editor writes its attachments to.
     *
     * Must match PageResource's fileAttachmentsDirectory(); a test asserts it.
     */
    public const string DIRECTORY = 'page-body';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $disk = $this->imageDisk();

        $files = Storage::disk($disk)->files(self::DIRECTORY);

        if ($files === []) {
            $this->info('No page-body uploads found.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $cutoff = now()->subHours($this->retentionHours())->getTimestamp();

        $adopted = 0;
        $deleted = 0;
        $kept = 0;

        foreach ($files as $path) {
            // A file already adopted on an earlier run must never be adopted
            // again: that would write a second row and stage a second copy
            // every run -- with a worker down, the daily schedule would
            // accumulate one of each per day, which is precisely the leak this
            // command exists to prevent.
            //
            // It is not finished with, though. Adoption only rewrites the body
            // once the conversions exist, so a row whose job has since
            // completed is one this run has to pick up -- otherwise the page
            // keeps serving the original (EXIF and all) and the file can never
            // be deleted, which is both halves of what this command is for.
            $existing = $this->adoptedRow($path);

            if ($existing !== null) {
                $pages = $this->pagesReferencing($path);

                if ($pages === []) {
                    $this->line("keep:   {$path} (adopted, no longer referenced)");
                    $kept++;

                    continue;
                }

                if (! $existing->isImage()) {
                    $this->line("wait:   {$path} (adopted, awaiting processing)");
                    $kept++;

                    continue;
                }

                $this->line("resume: {$path} (adopted, rewriting ".count($pages).')');

                if (! $dryRun && $this->rewriteTo($existing, $path, $pages, $disk)) {
                    $adopted++;
                }

                continue;
            }

            $pages = $this->pagesReferencing($path);

            if ($pages !== []) {
                $this->line("adopt:  {$path} (referenced by ".count($pages).')');

                if (! $dryRun && $this->adopt($path, $pages, $disk)) {
                    $adopted++;
                }

                continue;
            }

            // A file uploaded moments ago has no reference yet because the body
            // holding it has not been saved. Deleting it here would take the
            // image out of an author's hands mid-edit.
            //
            // Strictly greater than, so --hours=0 means "collect everything
            // unreferenced now" rather than sparing a file whose timestamp
            // happens to equal the cutoff. This differs from
            // app:prune-orphaned-media, where 0 disables pruning: there the
            // option sets a retention policy, here it is a sweep instruction.
            if (Storage::disk($disk)->lastModified($path) > $cutoff) {
                $this->line("keep:   {$path} (inside grace period)");
                $kept++;

                continue;
            }

            $this->line("delete: {$path} (unreferenced)");

            if (! $dryRun) {
                Storage::disk($disk)->delete($path);
                $deleted++;
            }
        }

        $this->newLine();
        $this->info($dryRun
            ? 'Dry run: nothing was changed.'
            : "Adopted {$adopted}, deleted {$deleted}, kept {$kept}.");

        return self::SUCCESS;
    }

    /**
     * Adopt one referenced file, rewriting the bodies that point at it.
     *
     * The rewrite and the row are committed together: a body left pointing at
     * the old URL after the original is deleted is a broken image on a live
     * page, which is the one outcome worth a transaction here.
     *
     * @param  array<int, Page>  $pages
     */
    protected function adopt(string $path, array $pages, string $disk): bool
    {
        try {
            $media = $this->processInto($path, $pages[0], $disk);
        } catch (Throwable $e) {
            $this->error("  failed to process {$path}: {$e->getMessage()}");

            return false;
        }

        // The processing job runs on a worker, so the conversions may not exist
        // yet. Rewriting to a URL nothing has written would blank the image
        // until the worker caught up, so the file is left in place and the next
        // run picks it up once the row is ready.
        $media->refresh();

        if (! $media->isImage()) {
            $this->line('  queued for processing; will rewrite on the next run');

            return false;
        }

        return $this->rewriteTo($media, $path, $pages, $disk);
    }

    /**
     * Point every body at the processed file, then drop the original.
     *
     * Split out of adopt() because it is also the whole of what a resumed run
     * has left to do: the row and the conversions already exist, so re-running
     * processInto() would only duplicate them.
     *
     * The rewrite and the deletion are ordered, not atomic -- a body left
     * pointing at a deleted file is a broken image on a live page, whereas the
     * reverse just means the next run tries again.
     *
     * @param  array<int, Page>  $pages
     */
    protected function rewriteTo(Media $media, string $path, array $pages, string $disk): bool
    {
        $replacement = $media->url('wide') ?? $media->url();

        if ($replacement === null) {
            $this->line('  processed file is not readable yet; leaving in place');

            return false;
        }

        DB::transaction(function () use ($pages, $path, $replacement): void {
            foreach ($pages as $page) {
                $page->forceFill([
                    'body' => $this->rewriteBody((string) $page->body, $path, $replacement),
                ])->save();
            }
        });

        // Only once no body names it any more.
        Storage::disk($disk)->delete($path);

        return true;
    }

    /**
     * The row an earlier run created for this file, if there is one.
     *
     * Matched on file_name, which processInto() sets to the ORIGINAL basename
     * rather than the uuid the staged copy carries. That makes the row say
     * where it came from, which is both more useful in the library than a hash
     * and the only link back to the file still sitting on the public disk --
     * the row's own path is a fresh uuid that resembles nothing.
     *
     * Returns the row rather than a bool because the caller has to tell a job
     * still queued from one that has finished: the first is left alone, the
     * second still owes the body a rewrite.
     */
    protected function adoptedRow(string $path): ?Media
    {
        return Media::query()
            ->inCollection(MediaCollection::PageImage)
            ->where('file_name', basename($path))
            ->first();
    }

    /**
     * Copy a public file back to staging and hand it to the media manager.
     *
     * Staged rather than adopted in place because the manager's whole contract
     * is that the bytes it publishes are ones the processing job produced --
     * handing it a path on the public disk would publish the original.
     */
    protected function processInto(string $path, Page $page, string $disk): Media
    {
        $contents = Storage::disk($disk)->get($path);

        if ($contents === null) {
            throw new \RuntimeException("unable to read {$path}");
        }

        $staged = StagedUpload::DIRECTORY.'/'.Str::uuid()->toString();

        Storage::disk('local')->put($staged, $contents);

        $media = app(MediaManager::class)->attachStagedImage(
            stagedPath: $staged,
            collection: MediaCollection::PageImage,
            owner: $page,
        );

        // The manager names the row after the staged file, which is a uuid.
        // Recording where it actually came from is what lets alreadyAdopted()
        // recognise it on the next run, and it reads better in the library.
        $media->forceFill(['file_name' => basename($path)])->save();

        return $media;
    }

    /**
     * Swap every URL form of a stored path for its replacement.
     *
     * A body may name the same file as "/storage/x.png", as a full URL with the
     * host that was current when it was pasted, or with the disk's configured
     * URL. All three have been seen in this application, so all three are
     * rewritten rather than assuming the tidy one.
     */
    protected function rewriteBody(string $body, string $path, string $replacement): string
    {
        $candidates = array_unique([
            Storage::disk($this->imageDisk())->url($path),
            '/storage/'.$path,
            url('/storage/'.$path),
            config('app.url').'/storage/'.$path,
        ]);

        foreach ($candidates as $candidate) {
            $body = str_replace($candidate, $replacement, $body);
        }

        return $body;
    }

    /**
     * The pages whose body still names this file.
     *
     * Matched on the stored path rather than a full URL, so a body written
     * against a different host still counts as a reference -- missing one would
     * delete a file a live page is using.
     *
     * @return array<int, Page>
     */
    protected function pagesReferencing(string $path): array
    {
        return Page::query()
            ->where('body', 'like', '%'.$path.'%')
            ->get()
            ->all();
    }

    /**
     * How long an unreferenced file is kept before it is collected.
     */
    protected function retentionHours(): int
    {
        $option = $this->option('hours');

        if ($option === null || $option === '') {
            return (int) config('media.orphan_retention_hours', 24);
        }

        return (int) $option;
    }

    /**
     * The disk the body editor writes to.
     */
    protected function imageDisk(): string
    {
        /** @var string $disk */
        $disk = config('images.disk');

        return $disk;
    }
}
