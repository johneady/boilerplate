<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Support\Facades\Session;
use SessionHandlerInterface;

/**
 * Delete expired rows from the database-backed session and cache tables.
 *
 * Both stores treat expiry as a read-time check: a row past its expiration is
 * ignored, never deleted. Nothing in Laravel prunes either table for the
 * database driver -- the cache store has no pruning at all, and session
 * garbage collection is left to PHP's probabilistic session GC, which never
 * fires here because Laravel drives sessions itself rather than through
 * php.ini's handler. Left alone, both tables grow for the life of the
 * application.
 *
 * Registered on the schedule in routes/console.php.
 */
class PruneExpiredStorage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:prune-expired-storage
                            {--sessions-only : Prune only the session table}
                            {--cache-only : Prune only the cache tables}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete expired rows from the database session and cache tables';

    /**
     * Execute the console command.
     */
    public function handle(ConnectionResolverInterface $connections): int
    {
        if (! $this->option('cache-only')) {
            $this->pruneSessions();
        }

        if (! $this->option('sessions-only')) {
            $this->pruneCache($connections);
        }

        return self::SUCCESS;
    }

    /**
     * Delete sessions whose last activity is older than the session lifetime.
     *
     * Delegated to the configured handler's own gc() rather than a hand-rolled
     * delete, so the table name, connection and expiry rule stay defined in
     * exactly one place.
     */
    private function pruneSessions(): void
    {
        if (config('session.driver') !== 'database') {
            $this->components->info('Session driver is not [database]; skipping.');

            return;
        }

        $handler = Session::driver()->getHandler();

        // gc() is declared by PHP's own SessionHandlerInterface, which every
        // Laravel session handler implements; the check is what tells the
        // static analyser so, and covers a custom handler that does not.
        if (! $handler instanceof SessionHandlerInterface) {
            $this->components->info('Session handler cannot be garbage collected; skipping.');

            return;
        }

        $lifetime = (int) config('session.lifetime', 120) * 60;

        $deleted = (int) $handler->gc($lifetime);

        $this->components->info("Pruned {$deleted} expired session(s).");
    }

    /**
     * Delete cache entries and cache locks that have passed their expiration.
     *
     * Both tables index `expiration`, so this stays an indexed range delete
     * however large the table has grown. A lock row is deleted on release,
     * making leftovers the residue of a process killed mid-lock -- the very
     * rows that would otherwise block a scheduled task forever.
     */
    private function pruneCache(ConnectionResolverInterface $connections): void
    {
        $store = config('cache.default');

        if (config("cache.stores.{$store}.driver") !== 'database') {
            $this->components->info('Cache store is not [database]; skipping.');

            return;
        }

        $table = config("cache.stores.{$store}.table") ?: 'cache';
        $lockTable = config("cache.stores.{$store}.lock_table") ?: 'cache_locks';

        // The lock table may live on its own connection; ?: rather than ??
        // throughout because these config keys default to null, not absent.
        $connection = $connections->connection(config("cache.stores.{$store}.connection"));
        $lockConnection = $connections->connection(
            config("cache.stores.{$store}.lock_connection") ?: config("cache.stores.{$store}.connection"),
        );

        $now = now()->getTimestamp();

        $entries = $connection->table($table)->where('expiration', '<=', $now)->delete();
        $locks = $lockConnection->table($lockTable)->where('expiration', '<=', $now)->delete();

        $this->components->info("Pruned {$entries} expired cache entr(ies) and {$locks} stale lock(s).");
    }
}
