<?php

namespace App\Perfumes;

/**
 * What an import did with one row. The values name the PerfumeImport counter
 * each outcome increments (rows_created, rows_updated, rows_unchanged).
 */
enum RowOutcome: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Unchanged = 'unchanged';
}
