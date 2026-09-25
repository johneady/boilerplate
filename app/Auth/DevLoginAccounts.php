<?php

namespace App\Auth;

use App\Models\User;
use Filament\Facades\Filament;

class DevLoginAccounts
{
    /**
     * Determine whether quick dev logins are available.
     *
     * APP_ENV is the only gate, and it is a denylist: every environment offers
     * the one-click logins except those named in config/dev-login.php, so a
     * bespoke environment name works without being registered first.
     * routes/web.php skips registering the route entirely where this is false.
     *
     * Fails CLOSED on a missing or empty config: a blocked list that resolves
     * to nothing would otherwise turn passwordless login on in production --
     * exactly the environment the list exists to exclude.
     */
    public function enabled(): bool
    {
        $blocked = array_filter(array_map(
            fn (mixed $environment): string => trim((string) $environment),
            (array) config('dev-login.blocked_environments', ['production']),
        ));

        if ($blocked === []) {
            $blocked = ['production'];
        }

        return ! app()->environment($blocked);
    }

    /**
     * Resolve the configured account at the given position in the list.
     *
     * Callers hand over a position rather than an email so that a request can
     * only ever select an account the application itself offered. Positions
     * are the account's index in config/dev-login.php and stay fixed even if
     * an earlier entry is unusable.
     */
    public function find(int $index): ?User
    {
        $accounts = $this->configured();

        if (! array_key_exists($index, $accounts)) {
            return null;
        }

        return User::where('email', $accounts[$index]['email'])->first();
    }

    /**
     * Describe the accounts offered as quick dev logins.
     *
     * Each entry reports the account's real role rather than assuming the
     * seeded first user is an admin. A first user created by the factory
     * instead of AdminUserSeeder is not promoted, and badging it as an
     * administrator regardless would hide why the login lands on the
     * dashboard. `panel` is whether that role works in the admin panel, which
     * is where the login will land.
     *
     * @return list<array{index: int, email: string, name: string, exists: bool, role: ?Role, panel: bool}>
     */
    public function all(): array
    {
        $accounts = $this->configured();

        $users = User::whereIn('email', array_column($accounts, 'email'))
            ->get()
            ->keyBy('email');

        // Looked up rather than getPanel('admin'), which throws for an
        // unregistered id -- see ResolvesLoginRedirect::adminPanel().
        $panel = Filament::getPanels()['admin'] ?? null;

        $described = [];

        foreach ($accounts as $index => $account) {
            $user = $users->get($account['email']);

            $described[] = [
                'index' => $index,
                'email' => $account['email'],
                'name' => $user->name ?? $account['name'],
                'exists' => $user !== null,
                'role' => $user?->role,
                'panel' => $user !== null && $panel !== null && $user->canAccessPanel($panel),
            ];
        }

        return $described;
    }

    /**
     * Read the configured accounts, keyed by their position in the config.
     *
     * An account left without an email falls back to the seeded first user,
     * so the admin account stays described by config/first.php alone.
     *
     * Unusable entries are dropped but the surviving keys keep their original
     * positions rather than being renumbered: the position is what a submitted
     * form names, so renumbering would silently point an in-flight request at
     * a different account.
     *
     * @return array<int, array{email: string, name: string}>
     */
    private function configured(): array
    {
        $accounts = [];

        foreach (array_values((array) config('dev-login.accounts')) as $index => $account) {
            $email = trim((string) ($account['email'] ?? config('first.user.email')));

            if ($email === '') {
                continue;
            }

            $name = trim((string) ($account['name'] ?? config('first.user.name')));

            $accounts[$index] = [
                'email' => $email,
                'name' => $name !== '' ? $name : $email,
            ];
        }

        return $accounts;
    }
}
