<?php

namespace App\Settings;

use Illuminate\Support\Facades\DB;
use PDO;
use Throwable;

/**
 * Report on the server and runtime the application is running on.
 *
 * Where ProductionDiagnostics judges the configuration, this describes the
 * machine: the PHP build, the database server, the disk the project lives
 * on. Every row degrades to a blank rather than an error -- on shared
 * hosting OPcache is often absent, the web server's name is not always
 * passed through, and the database server may not expose its size -- so the
 * report says only what the request handling it can actually see.
 *
 * Read at render time and never cached, deliberately: the point is what
 * this request can see right now, not what an earlier one saw.
 */
class ServerInformation
{
    /**
     * The report the Server tab renders, in display order.
     *
     * @return array{
     *     sections: list<array{title: string, rows: array<string, string|null>, usedPercent?: float|null}>,
     *     extensions: list<string>,
     * }
     */
    public function toArray(): array
    {
        $disk = $this->disk();

        return [
            'sections' => [
                ['title' => 'Platform', 'rows' => $this->platform()],
                ['title' => 'PHP & Laravel', 'rows' => $this->runtime()],
                ['title' => 'OPcache', 'rows' => $this->opcache()],
                ['title' => 'Database', 'rows' => $this->database()],
                ['title' => 'Disk', 'rows' => $disk['rows'], 'usedPercent' => $disk['usedPercent']],
            ],
            'extensions' => $this->extensions(),
        ];
    }

    /**
     * The machine and the software serving requests.
     *
     * @return array<string, string|null>
     */
    public function platform(): array
    {
        return [
            'Operating system' => PHP_OS_FAMILY,
            'Kernel' => self::optional(php_uname('r')),
            'Architecture' => self::optional(php_uname('m')),
            'Hostname' => self::optional(gethostname()),
            'Web server' => self::optional($_SERVER['SERVER_SOFTWARE'] ?? null),
            'PHP interface' => PHP_SAPI,
            'Server clock' => now()->format('Y-m-d H:i').' ('.date_default_timezone_get().')',
        ];
    }

    /**
     * The PHP and framework build the request is running under.
     *
     * @return array<string, string|null>
     */
    public function runtime(): array
    {
        return [
            'PHP version' => PHP_VERSION,
            'Laravel' => app()->version(),
            'Memory limit' => static::describeByteLimit(ini_get('memory_limit')),
            'Upload limit' => static::describeByteLimit(ini_get('upload_max_filesize')),
            'Post limit' => static::describeByteLimit(ini_get('post_max_size')),
            'Execution time' => static::describeTimeLimit(ini_get('max_execution_time')),
            'Loaded extensions' => (string) count($this->extensions()),
        ];
    }

    /**
     * The opcode cache, where one exists to describe.
     *
     * "Not installed" is itself worth reporting: a host without OPcache is
     * executing PHP from source on every request, and this is the only
     * place an administrator would notice.
     *
     * @return array<string, string|null>
     */
    public function opcache(): array
    {
        if (! extension_loaded('Zend OPcache')) {
            return ['Status' => 'Not installed'];
        }

        // opcache_get_status reports false when the extension is present
        // but disabled -- the CLI default without opcache.enable_cli.
        $status = function_exists('opcache_get_status') ? opcache_get_status(false) : false;

        if ($status === false) {
            return ['Status' => 'Not enabled'];
        }

        $rows = ['Status' => 'Enabled'];

        $statistics = $status['opcache_statistics'] ?? [];
        $memory = $status['memory_usage'] ?? [];

        if (isset($statistics['hit_rate'])) {
            $rows['Hit rate'] = number_format((float) $statistics['hit_rate'], 1).'%';
        }

        if (isset($statistics['num_cached_scripts'])) {
            $rows['Cached scripts'] = number_format((int) $statistics['num_cached_scripts']);
        }

        $used = $memory['used_memory'] ?? null;
        $free = $memory['free_memory'] ?? null;

        if (is_numeric($used) && is_numeric($free)) {
            $total = (int) $used + (int) $free;

            $rows['Memory'] = static::formatBytes((int) $used).' of '.static::formatBytes($total)
                .' ('.(int) round((int) $used / max($total, 1) * 100).'%)';
        }

        return $rows;
    }

    /**
     * The database server behind the default connection.
     *
     * @return array<string, string|null>
     */
    public function database(): array
    {
        try {
            $connection = DB::connection();

            $rows = [
                'Driver' => $connection->getDriverName(),
                'Server version' => self::optional($connection->getPdo()->getAttribute(PDO::ATTR_SERVER_VERSION)),
            ];

            $size = $this->databaseSize($connection->getDriverName());

            if ($size !== null) {
                $rows['Size'] = static::formatBytes($size);
            }

            return $rows;
        } catch (Throwable) {
            // Even the connection failing is a reportable fact: fall back
            // to the configured driver name, the one thing always known.
            $driver = config('database.connections.'.config('database.default').'.driver');

            return ['Driver' => is_string($driver) ? $driver : null];
        }
    }

    /**
     * The database's size on the server, when the driver can say.
     *
     * Server metadata has no model to ask through, so this reads it the way
     * an administrator would: information_schema for MySQL and MariaDB,
     * pg_database_size for PostgreSQL, the file on disk for SQLite. Drivers
     * without a route in -- or a host that refuses the question -- report
     * nothing rather than a guess.
     */
    private function databaseSize(string $driver): ?int
    {
        try {
            return match ($driver) {
                'sqlite' => $this->sqliteSize(),
                'mysql' => $this->mysqlSize(),
                'pgsql' => $this->pgsqlSize(),
                default => null,
            };
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * The SQLite database file's size, or nothing for a purely in-memory
     * database, which occupies no file to measure.
     */
    private function sqliteSize(): ?int
    {
        $database = (string) config('database.connections.sqlite.database');

        if ($database === '' || $database === ':memory:') {
            return null;
        }

        $size = is_file($database) ? filesize($database) : false;

        return $size === false ? null : (int) $size;
    }

    private function mysqlSize(): ?int
    {
        $result = DB::selectOne(
            'select sum(data_length + index_length) as size
                from information_schema.tables
                where table_schema = database()',
        );

        $size = $result->size ?? null;

        return is_numeric($size) ? (int) $size : null;
    }

    private function pgsqlSize(): ?int
    {
        $result = DB::selectOne('select pg_database_size(current_database()) as size');

        $size = $result->size ?? null;

        return is_numeric($size) ? (int) $size : null;
    }

    /**
     * The filesystem the project itself lives on, measured from its root.
     *
     * @return array{rows: array<string, string|null>, usedPercent: float|null}
     */
    public function disk(): array
    {
        $total = disk_total_space(base_path());
        $free = disk_free_space(base_path());

        $rows = [
            'Total' => static::formatBytes($total === false ? null : (int) $total),
            'Available' => static::formatBytes($free === false ? null : (int) $free),
        ];

        $usedPercent = $total !== false && $free !== false && $total > 0
            ? round(($total - $free) / $total * 100, 1)
            : null;

        if ($usedPercent !== null) {
            $rows['Used'] = $usedPercent.'%';
        }

        return ['rows' => $rows, 'usedPercent' => $usedPercent];
    }

    /**
     * The PHP extensions this build runs with, alphabetised.
     *
     * @return list<string>
     */
    public function extensions(): array
    {
        $extensions = get_loaded_extensions();

        sort($extensions);

        return $extensions;
    }

    /**
     * A php.ini size directive as a human-readable limit.
     *
     * PHP writes both "128M" and "-1" (unlimited) in these settings.
     */
    public static function describeByteLimit(string|false|null $value): ?string
    {
        $value = self::optional($value);

        if ($value === null) {
            return null;
        }

        return $value === '-1' ? 'Unlimited' : static::formatBytes(static::toBytes($value));
    }

    /**
     * A php.ini seconds directive as a human-readable limit.
     *
     * Zero means unlimited here -- the CLI's max_execution_time, most
     * commonly -- rather than "dies instantly".
     */
    public static function describeTimeLimit(string|false|null $value): ?string
    {
        $value = self::optional($value);

        if ($value === null) {
            return null;
        }

        return $value === '0' ? 'Unlimited' : $value.' seconds';
    }

    /**
     * A php.ini shorthand value ("128M", "2G", "1024") as bytes.
     */
    public static function toBytes(string $value): ?int
    {
        if (! preg_match('/^(\d+)([kmgtpe]?)$/i', trim($value), $matches)) {
            return null;
        }

        /** @var array<string, int> $exponents */
        static $exponents = [
            '' => 0, 'k' => 1, 'm' => 2, 'g' => 3, 't' => 4, 'p' => 5, 'e' => 6,
        ];

        return (int) $matches[1] * 1024 ** $exponents[strtolower($matches[2])];
    }

    /**
     * Bytes as the size a person reads, binary units written the familiar
     * way ("512 B", "128 MB", "1.5 GB").
     */
    public static function formatBytes(?int $bytes): ?string
    {
        if ($bytes === null) {
            return null;
        }

        if ($bytes === 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];

        // Clamped so a size beyond the largest unit still formats, in that
        // unit, rather than indexing past the list.
        $exponent = min((int) floor(log($bytes, 1024)), count($units) - 1);

        $value = $bytes / 1024 ** $exponent;

        $formatted = $value >= 10 || $exponent === 0 ? (string) round($value) : (string) round($value, 1);

        return $formatted.' '.$units[$exponent];
    }

    /**
     * A value as a displayable string, null when the server offered none.
     *
     * The PHP functions this normalises return false -- not null -- for
     * "could not be read", and pad some values with whitespace.
     */
    private static function optional(string|false|null $value): ?string
    {
        if ($value === false || $value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
