<?php

namespace App\View\Composers;

use App\Models\User;
use Illuminate\View\View;

class DevLoginLinksComposer
{
    /**
     * Bind the quick dev login accounts to the view.
     */
    public function compose(View $view): void
    {
        $view->with('devUsers', $this->devUsers());
    }

    /**
     * Describe the accounts offered as quick dev logins.
     *
     * Each entry reports the account's real is_admin flag rather than assuming
     * the seeded first user is an admin. A first user created by the factory
     * instead of AdminUserSeeder is not promoted, and labelling it "Admin
     * panel" regardless would hide why the login lands on the dashboard.
     *
     * @return list<array{email: string, name: string, exists: bool, admin: bool}>
     */
    private function devUsers(): array
    {
        $candidates = [
            ['email' => (string) config('first.user.email'), 'name' => (string) config('first.user.name')],
            ['email' => 'test@example.com', 'name' => 'Test User'],
        ];

        $users = User::whereIn('email', array_column($candidates, 'email'))
            ->get()
            ->keyBy('email');

        return array_map(function (array $candidate) use ($users): array {
            $user = $users->get($candidate['email']);

            return [
                'email' => $candidate['email'],
                'name' => $user->name ?? $candidate['name'],
                'exists' => $user !== null,
                'admin' => (bool) $user?->is_admin,
            ];
        }, $candidates);
    }
}
