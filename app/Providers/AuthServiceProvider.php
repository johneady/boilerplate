<?php

namespace App\Providers;

use App\Auth\Permission;
use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Abilities the administrator bypass must NOT answer.
     *
     * These are the rules that deny an administrator on purpose -- deleting
     * your own account, demoting yourself -- so a blanket `return true` for
     * admins would silently undo them. Gate::before runs before every policy
     * and short-circuits on any non-null return, which makes it exactly the
     * wrong place to answer them: listing them here sends them on to the
     * policy instead.
     *
     * Keep this in step with the self-protection rules in App\Policies.
     *
     * @var list<string>
     */
    private const array SELF_PROTECTED_ABILITIES = [
        'delete',
        'forceDelete',
        'updateRole',
    ];

    /**
     * The policy for each model.
     *
     * Registered explicitly rather than relying on Laravel's convention-based
     * discovery: discovery maps App\Models\X to App\Policies\XPolicy by
     * naming alone, so a renamed or relocated policy stops being applied with
     * no error at all -- every ability silently falls through to deny (or to
     * the admin bypass, which is worse, because it looks like it works while
     * testing as an administrator).
     *
     * @var array<class-string, class-string>
     */
    private const array POLICIES = [
        User::class => UserPolicy::class,
    ];

    /**
     * Register any authentication and authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
        $this->registerPermissionAbilities();
        $this->registerAdministratorBypass();
    }

    /**
     * Bind each model to its policy.
     */
    private function registerPolicies(): void
    {
        foreach (self::POLICIES as $model => $policy) {
            Gate::policy($model, $policy);
        }
    }

    /**
     * Register every permission as an ability of its own.
     *
     * This is what lets a check that has no model behind it -- "may this user
     * reach the settings screen" -- go through the Gate like any other:
     * `$user->can(Permission::ManageSettings)` and the Blade
     * `@can(Permission::ManageSettings)` both resolve here, because Gate
     * stringifies the enum through its backed value.
     */
    private function registerPermissionAbilities(): void
    {
        foreach (Permission::cases() as $permission) {
            Gate::define($permission->value, fn (User $user): bool => $user->hasPermission($permission));
        }
    }

    /**
     * Let administrators through every ability except the self-protected ones.
     *
     * The bypass exists so a new model does not need its policy written before
     * administrators can manage it, and so an ability someone forgot to map in
     * a policy is not accidentally denied to them.
     *
     * The exemption is deliberately scoped to checks made AGAINST THE USER
     * THEMSELVES, not to the ability name alone. Exempting the bare name would
     * stand the bypass down for `delete` on every model in the application --
     * a Setting, a Post, anything whose policy has not been written yet would
     * start denying administrators, which is precisely what the bypass exists
     * to prevent. Only "am I doing this to my own account" needs the policy.
     *
     * Returning null rather than false for everyone else is essential: false
     * would deny the ability outright and no policy would ever be consulted.
     */
    private function registerAdministratorBypass(): void
    {
        Gate::before(function (User $user, string $ability, array $arguments = []): ?bool {
            if (! $user->is_admin) {
                return null;
            }

            if ($this->isSelfProtected($user, $ability, $arguments)) {
                return null;
            }

            return true;
        });
    }

    /**
     * Whether this check is one the administrator bypass must not answer.
     *
     * True only when a self-protected ability is being checked against the
     * acting user's own account, which is the single case UserPolicy denies
     * an administrator on purpose.
     *
     * @param  array<int, mixed>  $arguments
     */
    private function isSelfProtected(User $user, string $ability, array $arguments): bool
    {
        if (! in_array($ability, self::SELF_PROTECTED_ABILITIES, true)) {
            return false;
        }

        $target = $arguments[0] ?? null;

        return $target instanceof User && $user->is($target);
    }
}
