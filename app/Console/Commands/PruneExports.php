<?php

namespace App\Console\Commands;

use App\Filament\Exports\UserExporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Delete finished exports, and their files, once they are a week old.
 *
 * An export is a spreadsheet of customers' names, emails and payments, and
 * Filament never deletes one: the file stays on disk for as long as the
 * installation lives. A week is long enough to download it from the
 * notification that announced it.
 *
 * Also sweeps export directories with no row at all. Deleting a user cascades
 * away their export rows (the exports table's foreign key) but cannot reach
 * the files, which would otherwise be left with nothing pointing at them.
 *
 * And the panel notifications announcing the exports it deletes: their
 * Download buttons would otherwise lead to a 404 from the bell for good.
 *
 * Registered on the schedule in routes/console.php.
 */
class PruneExports extends Command
{
    /**
     * How long an export is kept, in days.
     */
    private const int RETENTION_DAYS = 7;

    /**
     * Where Filament writes each export, one directory per export id.
     *
     * @see Export::getFileDirectory()
     */
    private const string DIRECTORY = 'filament_exports';

    protected $signature = 'app:prune-exports';

    protected $description = 'Delete exports older than a week, and their files';

    public function handle(): int
    {
        $pruned = 0;

        Export::query()
            ->where('created_at', '<', now()->subDays(self::RETENTION_DAYS))
            ->chunkById(100, function ($exports) use (&$pruned): void {
                foreach ($exports as $export) {
                    Storage::disk($export->file_disk)->deleteDirectory($export->getFileDirectory());
                    $export->delete();
                    $pruned++;
                }
            });

        $orphaned = $this->sweepOrphanedDirectories();

        // The announcement's Download button carries the export's download
        // URL, so an old one naming that route is spent. Matched without
        // slashes: json_encode stores them escaped.
        DB::table('notifications')
            ->where('created_at', '<', now()->subDays(self::RETENTION_DAYS))
            ->where('data', 'like', '%exports%download%')
            ->delete();

        $this->components->info("Pruned {$pruned} ".str('export')->plural($pruned).", and {$orphaned} orphaned ".str('directory')->plural($orphaned).'.');

        return self::SUCCESS;
    }

    /**
     * Delete export directories whose row no longer exists.
     *
     * A new export's row is written before its directory, so a directory
     * without a row is never one still being filled.
     */
    private function sweepOrphanedDirectories(): int
    {
        // The disks exports were written to, and the one new exports go to --
        // which is where orphans are found once their rows are all gone.
        $disks = Export::query()->distinct()->pluck('file_disk')
            ->push((new UserExporter(new Export, [], []))->getFileDisk())
            ->unique();
        $existing = Export::query()->pluck('id')->flip();
        $swept = 0;

        foreach ($disks as $disk) {
            $storage = Storage::disk($disk);

            foreach ($storage->directories(self::DIRECTORY) as $directory) {
                $id = basename($directory);

                if (! ctype_digit($id) || $existing->has((int) $id)) {
                    continue;
                }

                $storage->deleteDirectory($directory);
                $swept++;
            }
        }

        return $swept;
    }
}
