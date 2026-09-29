<?php

namespace App\Perfumes;

use App\Models\Brand;
use App\Models\Perfume;
use App\Models\PerfumeImport;
use App\Models\User;
use App\Perfumes\Enums\Audience;
use App\Perfumes\Enums\Concentration;
use App\Perfumes\Enums\Family;
use App\Perfumes\Enums\ImportStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use SplFileObject;

/**
 * Refreshes the perfume database from a CSV file.
 *
 * Every row is upserted on its external_id, the key the upstream source gives
 * it: a new id creates a perfume, a known id updates it in place, and a row
 * identical to what is stored is counted as unchanged. Nothing is deleted, so
 * a partial file (just this month's additions) is as safe to run as a full
 * export, and a perfume's followers and view history always survive.
 *
 * A bad row is recorded against its line number and skipped rather than
 * failing the file, so one typo does not block a thousand good rows.
 */
class PerfumeImporter
{
    /**
     * Columns a file must carry. Everything else is optional.
     *
     * @var list<string>
     */
    public const array REQUIRED_COLUMNS = ['external_id', 'brand', 'name'];

    /**
     * The template's columns, in order. Also what the admin downloads.
     *
     * @var list<string>
     */
    public const array COLUMNS = [
        'external_id', 'brand', 'brand_country', 'name', 'release_year', 'concentration', 'gender', 'family',
        'perfumer', 'top_notes', 'heart_notes', 'base_notes', 'description',
    ];

    /**
     * Header spellings accepted for each column, so a Supabase or spreadsheet
     * export can be imported without renaming its headers first.
     *
     * @var array<string, string>
     */
    private const array ALIASES = [
        'id' => 'external_id',
        'uuid' => 'external_id',
        'house' => 'brand',
        'brand_name' => 'brand',
        'country' => 'brand_country',
        'perfume' => 'name',
        'title' => 'name',
        'year' => 'release_year',
        'launched' => 'release_year',
        'type' => 'concentration',
        'audience' => 'gender',
        'olfactory_family' => 'family',
        'nose' => 'perfumer',
        'notes_top' => 'top_notes',
        'notes_heart' => 'heart_notes',
        'middle_notes' => 'heart_notes',
        'notes_base' => 'base_notes',
    ];

    /**
     * How many row errors are kept on the import record. The counts stay
     * exact; only the listing is capped, so a wholly wrong file cannot write
     * a megabyte of identical messages.
     */
    private const int MAX_RECORDED_ERRORS = 50;

    /**
     * @var array<string, Brand>
     */
    private array $brands = [];

    /**
     * Import a CSV file and return the record of what it changed.
     */
    public function import(string $path, string $fileName, ?User $user = null): PerfumeImport
    {
        $import = PerfumeImport::create([
            'file_name' => $fileName,
            'status' => ImportStatus::Running,
            'rows_total' => 0,
            'rows_created' => 0,
            'rows_updated' => 0,
            'rows_unchanged' => 0,
            'rows_failed' => 0,
            'user_id' => $user?->id,
        ]);

        $errors = [];

        try {
            $file = new SplFileObject($path);
        } catch (RuntimeException) {
            return $this->finish($import, ImportStatus::Failed, ['file' => 'The file could not be read.']);
        }

        $header = $this->header($file->fgetcsv(escape: '') ?: []);
        $missing = array_diff(self::REQUIRED_COLUMNS, $header);

        if ($missing !== []) {
            return $this->finish($import, ImportStatus::Failed, [
                'header' => 'Missing required column(s): '.implode(', ', $missing).'.',
            ]);
        }

        DB::transaction(function () use ($file, $header, $import, &$errors): void {
            $line = 1;

            while (! $file->eof()) {
                $cells = $file->fgetcsv(escape: '');
                $line++;

                if (! is_array($cells) || $cells === [null] || array_filter($cells, fn ($cell) => trim((string) $cell) !== '') === []) {
                    continue;
                }

                $import->rows_total++;
                $row = array_combine($header, array_pad(array_slice($cells, 0, count($header)), count($header), ''));
                $outcome = $this->importRow(array_map(fn ($cell): string => trim((string) $cell), $row));

                if (is_string($outcome)) {
                    $import->rows_failed++;

                    if (count($errors) < self::MAX_RECORDED_ERRORS) {
                        $errors[$line] = $outcome;
                    }

                    continue;
                }

                $import->{'rows_'.$outcome->value}++;
            }
        });

        return $this->finish(
            $import,
            $import->rows_failed > 0 ? ImportStatus::CompletedWithErrors : ImportStatus::Completed,
            $errors,
        );
    }

    /**
     * Upsert one row, returning what happened to it or why it was rejected.
     *
     * @param  array<string, string>  $row
     */
    private function importRow(array $row): RowOutcome|string
    {
        foreach (self::REQUIRED_COLUMNS as $column) {
            if (($row[$column] ?? '') === '') {
                return "The {$column} column is empty.";
            }
        }

        $year = $row['release_year'] ?? '';

        if ($year !== '' && (! ctype_digit($year) || (int) $year < 1700 || (int) $year > (int) date('Y') + 1)) {
            return "\"{$year}\" is not a release year.";
        }

        $attributes = [
            'name' => $row['name'],
            'release_year' => $year === '' ? null : (int) $year,
            'concentration' => $this->enum($row, 'concentration', Concentration::fromSource(...)),
            'gender' => $this->enum($row, 'gender', Audience::fromSource(...)),
            'family' => $this->enum($row, 'family', Family::fromSource(...)),
            'perfumer' => ($row['perfumer'] ?? '') ?: null,
            'top_notes' => $this->notes($row['top_notes'] ?? ''),
            'heart_notes' => $this->notes($row['heart_notes'] ?? ''),
            'base_notes' => $this->notes($row['base_notes'] ?? ''),
            'description' => ($row['description'] ?? '') ?: null,
        ];

        foreach (['concentration', 'gender', 'family'] as $column) {
            if ($attributes[$column] === false) {
                return "\"{$row[$column]}\" is not a recognised {$column}.";
            }
        }

        $brand = $this->brand($row['brand'], ($row['brand_country'] ?? '') ?: null);
        $attributes['brand_id'] = $brand->id;

        $perfume = Perfume::query()->firstOrNew(['external_id' => $row['external_id']]);
        $perfume->fill($attributes);

        if (! $perfume->exists) {
            $perfume->slug = $this->uniqueSlug($brand->name.' '.$row['name']);
            $perfume->save();

            return RowOutcome::Created;
        }

        // The slug is left alone on update: renaming a perfume must not
        // break links people have already shared or search engines indexed.
        if (! $perfume->isDirty()) {
            return RowOutcome::Unchanged;
        }

        $perfume->save();

        return RowOutcome::Updated;
    }

    /**
     * Normalise the header row: lower snake case, with known aliases mapped
     * onto the canonical column names.
     *
     * @param  array<int, string|null>  $cells
     * @return list<string>
     */
    private function header(array $cells): array
    {
        return array_values(array_map(function (?string $cell): string {
            $key = Str::snake(trim(str_replace("\u{FEFF}", '', (string) $cell)));
            $key = str_replace([' ', '-'], '_', $key);

            return self::ALIASES[$key] ?? $key;
        }, $cells));
    }

    /**
     * Resolve an optional enum column: null when blank, false when present
     * but not recognised (the caller rejects the row).
     *
     * @template T of \BackedEnum
     *
     * @param  array<string, string>  $row
     * @param  callable(string): (T|null)  $resolve
     * @return T|false|null
     */
    private function enum(array $row, string $column, callable $resolve): mixed
    {
        $value = $row[$column] ?? '';

        if ($value === '') {
            return null;
        }

        return $resolve($value) ?? false;
    }

    /**
     * Split a notes cell. Pipes, semicolons and commas are all accepted,
     * since spreadsheets and exports disagree on which to use.
     *
     * @return list<string>|null
     */
    private function notes(string $cell): ?array
    {
        $notes = array_values(array_unique(array_filter(
            array_map(fn (string $note): string => Str::ucfirst(trim($note)), preg_split('/[|;,]/', $cell) ?: []),
            fn (string $note): bool => $note !== '',
        )));

        return $notes === [] ? null : $notes;
    }

    private function brand(string $name, ?string $country): Brand
    {
        $key = mb_strtolower($name);

        if (! isset($this->brands[$key])) {
            $brand = Brand::query()->firstOrCreate(['name' => $name], ['slug' => Str::slug($name)]);

            if ($country !== null && $brand->country !== $country) {
                $brand->update(['country' => $country]);
            }

            $this->brands[$key] = $brand;
        }

        return $this->brands[$key];
    }

    private function uniqueSlug(string $source): string
    {
        $base = Str::slug($source) ?: 'perfume';
        $slug = $base;
        $suffix = 2;

        while (Perfume::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    /**
     * @param  array<int|string, string>  $errors
     */
    private function finish(PerfumeImport $import, ImportStatus $status, array $errors): PerfumeImport
    {
        $import->status = $status;
        $import->errors = $errors === [] ? null : $errors;
        $import->finished_at = now();
        $import->save();

        return $import;
    }
}
