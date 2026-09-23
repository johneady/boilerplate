<?php

namespace App\Providers;

use App\Auth\Permission;
use App\Models\Article;
use App\Models\AuditLog;
use App\Models\ContactSubmission;
use App\Models\Enquiry;
use App\Models\Media;
use App\Models\Page;
use App\Models\User;
use App\Models\Vehicle;
use App\Policies\ArticlePolicy;
use App\Policies\AuditLogPolicy;
use App\Policies\ContactSubmissionPolicy;
use App\Policies\EnquiryPolicy;
use App\Policies\MediaPolicy;
use App\Policies\PagePolicy;
use App\Policies\UserPolicy;
use App\Policies\VehiclePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Abilities the administrator bypass must NOT answer.
     *
     * These are the rules that deny an administrator on purpose -- deleting
     * your own account, demoting yourself, deleting an audit entry -- so a
     * blanket `return true` for
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
     * Abilities the bypass must not answer for an immutable record.
     *
     * Every way of writing to one. An audit entry may be read by anyone
     * holding the permission and changed by nobody at all -- see
     * App\Policies\AuditLogPolicy -- so the bypass has to stand down for the
     * whole set rather than for deletion alone, which would leave an
     * administrator able to edit an entry instead of removing it.
     *
     * `viewAny` and `view` are deliberately absent: those are the abilities
     * administrators should keep passing through the bypass for.
     *
     * @var list<string>
     */
    private const array IMMUTABLE_RECORD_ABILITIES = [
        'create',
        'update',
        'delete',
        'forceDelete',
        'restore',
        'replicate',
    ];

    /**
     * Abilities the bypass must not answer for a record written only by code.
     *
     * Narrower than IMMUTABLE_RECORD_ABILITIES, and deliberately so: a media
     * row may be DELETED by an administrator -- that is how a file is removed,
     * and Media::delete() takes the bytes with it -- but it may never be
     * created or edited through the panel.
     *
     * Creating one means uploading, which goes through App\Media\MediaManager
     * so the bytes are validated, staged privately and re-encoded. A create
     * form would be a second way in that skipped all of it. Editing one cannot
     * move the bytes it describes, so it could only ever make the row disagree
     * with the file on disk.
     *
     * @var list<string>
     */
    private const array CODE_WRITTEN_RECORD_ABILITIES = [
        'create',
        'update',
        'replicate',
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
        Article::class => ArticlePolicy::class,
        AuditLog::class => AuditLogPolicy::class,
        ContactSubmission::class => ContactSubmissionPolicy::class,
        Enquiry::class => EnquiryPolicy::class,
        Media::class => MediaPolicy::class,
        Page::class => PagePolicy::class,
        User::class => UserPolicy::class,
        Vehicle::class => VehiclePolicy::class,
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
     * True in two cases: a self-protected ability checked against the acting
     * user's own account (UserPolicy's self-protection rules), and any of them
     * checked against an immutable record such as an audit entry.
     *
     * @param  array<int, mixed>  $arguments
     */
    private function isSelfProtected(User $user, string $ability, array $arguments): bool
    {
        $target = $arguments[0] ?? null;

        // Checked before the ability list, and against a different list: an
        // immutable record must stand the bypass down for WRITES of every
        // kind, not only the three self-protected abilities.
        if ($this->isImmutableRecord($target)) {
            return in_array($ability, self::IMMUTABLE_RECORD_ABILITIES, true);
        }

        if ($this->isCodeWrittenRecord($target)) {
            return in_array($ability, self::CODE_WRITTEN_RECORD_ABILITIES, true);
        }

        if (! in_array($ability, self::SELF_PROTECTED_ABILITIES, true)) {
            return false;
        }

        return $target instanceof User && $user->is($target);
    }

    /**
     * Whether the target is a record only code may create or edit.
     *
     * Media is the case. Matched on the class as well as an instance so the
     * class-form check Filament makes when deciding whether to render a "New"
     * button (`can('create', Media::class)`) is covered too -- that form passes
     * no model at all.
     */
    private function isCodeWrittenRecord(mixed $target): bool
    {
        if ($target instanceof Media) {
            return true;
        }

        return is_string($target) && is_a($target, Media::class, true);
    }

    /**
     * Whether the target is a record no one may delete, administrators included.
     *
     * Audit entries are the case: a trail an administrator can prune by hand
     * records only what they are willing to admit to, while still reading as
     * authoritative. AuditLogPolicy denies `delete` and `forceDelete` outright,
     * but a policy is never consulted for an administrator unless the bypass
     * stands down first -- which is what this does.
     *
     * Matched on the class rather than an instance check alone so the class
     * name passed to `$user->can('delete', AuditLog::class)` is covered too:
     * Filament asks that form of the question when deciding whether to render
     * a bulk delete action.
     */
    private function isImmutableRecord(mixed $target): bool
    {
        if ($target instanceof AuditLog) {
            return true;
        }

        return is_string($target) && is_a($target, AuditLog::class, true);
    }
}
