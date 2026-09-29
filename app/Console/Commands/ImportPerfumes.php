<?php

namespace App\Console\Commands;

use App\Perfumes\Enums\ImportStatus;
use App\Perfumes\PerfumeImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Refresh the perfume database from a CSV file or URL.
 *
 * With no argument it pulls config('perfumes.feed_url'), which is what the
 * scheduler runs nightly: point PERFUME_FEED_URL at a published sheet or an
 * export endpoint and the site refreshes itself. Unset, the scheduled run is
 * a no-op and refreshes happen by upload in the admin panel instead.
 */
class ImportPerfumes extends Command
{
    protected $signature = 'perfumes:import {source? : A CSV file path or http(s) URL; defaults to the configured feed}';

    protected $description = 'Create and update perfumes from a CSV file or feed URL';

    public function handle(PerfumeImporter $importer): int
    {
        $source = (string) ($this->argument('source') ?? config('perfumes.feed_url') ?? '');

        if ($source === '') {
            $this->components->info('No source given and no PERFUME_FEED_URL configured; nothing to import.');

            return self::SUCCESS;
        }

        $path = $source;

        if (preg_match('#^https?://#i', $source) === 1) {
            $response = Http::timeout(30)->get($source);

            if ($response->failed()) {
                $this->components->error("The feed returned HTTP {$response->status()}.");

                return self::FAILURE;
            }

            $path = (string) tempnam(sys_get_temp_dir(), 'perfume-feed');
            file_put_contents($path, $response->body());
        } elseif (! is_file($path)) {
            $this->components->error("No file at {$path}.");

            return self::FAILURE;
        }

        try {
            $import = $importer->import($path, basename((string) parse_url($source, PHP_URL_PATH)) ?: $source);
        } finally {
            if ($path !== $source) {
                @unlink($path);
            }
        }

        $this->components->twoColumnDetail('Status', $import->status->label());
        $this->components->twoColumnDetail('Created', (string) $import->rows_created);
        $this->components->twoColumnDetail('Updated', (string) $import->rows_updated);
        $this->components->twoColumnDetail('Unchanged', (string) $import->rows_unchanged);
        $this->components->twoColumnDetail('Failed', (string) $import->rows_failed);

        foreach ($import->errors ?? [] as $line => $error) {
            $this->components->warn(is_int($line) ? "Line {$line}: {$error}" : $error);
        }

        return $import->status !== ImportStatus::Completed ? self::FAILURE : self::SUCCESS;
    }
}
