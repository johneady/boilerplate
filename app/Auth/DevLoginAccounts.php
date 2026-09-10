<?php

namespace App\Auth;

use App\Models\User;

class DevLoginAccounts
{
    /**
     * Determine whether quick dev logins are available.
     *
     * APP_ENV is the only gate: `local` and `testing` offer them, every other
     * environment does not, and routes/web.php skips registering the route
     * entirely where this is false.
     */
    public function enabled(): bool
    {
        return app()->environment((array) config('dev-login.allowed_environments'));
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
     * Each entry reports the account's real is_admin flag rather than assuming
     * the seeded first user is an admin. A first user created by the factory
     * instead of AdminUserSeeder is not promoted, and labelling it "Admin
     * panel" regardless would hide why the login lands on the dashboard.
     *
     * @return list<array{index: int, email: string, name: string, exists: bool, admin: bool}>
     */
    public function all(): array
    {
        $accounts = $this->configured();

        $users = User::whereIn('email', array_column($accounts, 'email'))
            ->get()
            ->keyBy('email');

        $described = [];

        foreach ($accounts as $index => $account) {
            $user = $users->get($account['email']);

            $described[] = [
                'index' => $index,
                'email' => $account['email'],
                'name' => $user->name ?? $account['name'],
                'exists' => $user !== null,
                'admin' => (bool) $user?->is_admin,
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
