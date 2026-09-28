<?php

namespace App\Filament\Resources\Users;

use App\Auth\AccountRemovalRefused;
use App\Auth\Permission;
use App\Auth\Role;
use App\Filament\Exports\UserExporter;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Media\MediaCollection;
use App\Models\User;
use App\Payments\Exceptions\GatewayException;
use App\Settings\Settings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\ImageEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Read-only, and only while editing: creation has no record to
                // show one for, and an admin does not upload another person's
                // photo -- every avatar write goes through MediaManager on the
                // owner's own profile page.
                ImageEntry::make('avatar')
                    ->label(__('users.fields.avatar'))
                    ->hiddenOn('create')
                    ->getStateUsing(fn (User $record): ?string => static::avatarState($record, 'full'))
                    ->circular()
                    ->imageSize(96)
                    ->defaultImageUrl(fn (User $record): string => static::initialsAvatarUrl($record)),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label(__('users.fields.email'))
                    ->email()
                    ->required()
                    ->maxLength(255)
                    // Lowercased as User stores it, so the unique rule
                    // compares like with like on case-sensitive databases.
                    ->mutateStateForValidationUsing(fn (?string $state): ?string => $state === null ? null : Str::lower($state))
                    ->unique(ignoreRecord: true),
                // An admin editing themselves cannot drop their own rights:
                // doing so would lock them out of this panel on save, with no
                // way back in short of re-seeding or another admin's help.
                // UserPolicy::updateRole() is the same rule where the Gate can
                // see it, and saveUser() enforces it again server side.
                Select::make('role')
                    ->label(__('users.fields.role'))
                    ->options(fn (): array => collect(Role::cases())
                        ->mapWithKeys(fn (Role $role): array => [$role->value => __($role->label())])
                        ->all())
                    ->default(Role::DEFAULT->value)
                    ->selectablePlaceholder(false)
                    ->required()
                    ->disabled(fn (?User $record): bool => static::isCurrentUser($record))
                    ->helperText(fn (?User $record, $state): string => static::isCurrentUser($record)
                        ? __('users.own_role_locked')
                        : static::describeRole($state, $record)),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            // Only the avatar collection is eager-loaded, because the avatar
            // column below resolves through avatarUrl() -> getMedia(), which
            // would otherwise run one media query per row of this table.
            // getMedia() filters the loaded relation in memory, and skipping
            // the other collections keeps the eager load to one row per user.
            //
            // The three exists flags feed the per-row delete and deactivate
            // checks (User::hasFinancialRecords(), hasRunningSubscription()), which
            // would otherwise query once per row for each action rendered.
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with(['media' => fn ($media) => $media->inCollection(MediaCollection::Avatar)])
                ->withExists(['recordedPayments', 'initiatedRefunds', 'runningSubscription']))
            ->columns([
                // State is resolved through User::avatarUrl() rather than from
                // the media row directly: the row's `path` holds the conversion
                // DIRECTORY, not a file, and the accessor is what resolves the
                // named conversion. It also returns null while processing is
                // still in flight and when the conversion is missing, which is
                // what makes the initials fallback below correct rather than a
                // broken image.
                ImageColumn::make('avatar')
                    ->label(__('users.fields.avatar'))
                    ->getStateUsing(fn (User $record): ?string => static::avatarState($record))
                    ->circular()
                    // The cell is wrapped in the row-click button, whose only
                    // content is this image -- so without an alt the button has
                    // no accessible name at all (axe: button-name, critical).
                    // The user's name is what the button opens, so it is the
                    // right name for both.
                    ->alt(fn (User $record): string => $record->name)
                    ->defaultImageUrl(fn (User $record): string => static::initialsAvatarUrl($record)),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label(__('users.fields.email'))
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                TextColumn::make('role')
                    ->label(__('users.fields.role'))
                    ->badge()
                    ->formatStateUsing(fn (Role $state): string => __($state->label()))
                    ->color(fn (Role $state): string => $state->color())
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('users.status.label'))
                    ->state(fn (User $record): string => $record->isDeactivated()
                        ? __('users.status.deactivated')
                        : __('users.status.active'))
                    ->badge()
                    ->color(fn (User $record): string => $record->isDeactivated() ? 'danger' : 'success'),
                IconColumn::make('email_verified_at')
                    ->label(__('users.fields.verified'))
                    ->boolean()
                    ->sortable(),
                // Formatted through the settings service rather than
                // ->dateTime(): the Registered column is a wall-clock date
                // for an administrator, so it follows the display timezone,
                // format and locale settings the rest of the site does.
                TextColumn::make('created_at')
                    ->label(__('users.fields.registered'))
                    ->formatStateUsing(fn ($state): string => app(Settings::class)->formatDateTime($state))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('role')
                    ->label(__('users.fields.role'))
                    ->options(fn (): array => collect(Role::cases())
                        ->mapWithKeys(fn (Role $role): array => [$role->value => __($role->label())])
                        ->all()),
                TernaryFilter::make('email_verified_at')
                    ->label(__('users.fields.email_verified'))
                    ->nullable(),
                TernaryFilter::make('deactivated_at')
                    ->label(__('users.status.label'))
                    ->nullable()
                    ->trueLabel(__('users.status.deactivated'))
                    ->falseLabel(__('users.status.active')),
            ])
            ->headerActions([
                ExportAction::make()
                    ->label(__('users.export'))
                    ->exporter(UserExporter::class),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(function (User $record, array $data): User {
                        try {
                            return static::saveUser($record, $data);
                        } catch (AccountRemovalRefused) {
                            // Only reachable when another administrator was
                            // demoted or removed since this form opened.
                            Notification::make()->danger()->title(__('users.demote.last_administrator'))->send();

                            // What EditAction::halt() throws, raised here so
                            // the closure visibly never returns.
                            throw new Halt;
                        }
                    }),
                // How staff are offboarded: the account stops working but
                // stays, so their name stays on what they worked on. Shown
                // disabled for a customer with a running subscription, so the
                // administrator learns to cancel it first (UserPolicy).
                Action::make('deactivate')
                    ->label(__('users.deactivate.label'))
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading(__('users.deactivate.heading'))
                    ->modalDescription(__('users.deactivate.description'))
                    ->modalSubmitActionLabel(__('users.deactivate.label'))
                    ->hidden(fn (User $record): bool => $record->isDeactivated() || static::isCurrentUser($record))
                    ->authorize('deactivate')
                    ->authorizationTooltip(fn (User $record): bool => static::canRemoveAccounts() && $record->hasRunningSubscription())
                    ->authorizationMessage(__('users.deactivate.has_running_subscription'))
                    ->successNotificationTitle(__('users.deactivate.done'))
                    ->action(function (Action $action, User $record): void {
                        try {
                            $record->deactivate();
                        } catch (GatewayException $e) {
                            // An unfinished checkout could not be expired.
                            report($e);

                            Notification::make()->danger()->title(__('users.deactivate.gateway_failed'))->send();

                            return;
                        } catch (AccountRemovalRefused $e) {
                            // The account changed since the row rendered --
                            // a subscription started, say.
                            Notification::make()->danger()->title($e->protectsLastAdministrator()
                                ? __('users.deactivate.last_administrator')
                                : __('users.deactivate.has_running_subscription'))->send();

                            return;
                        }

                        $action->success();
                    }),
                Action::make('reactivate')
                    ->label(__('users.reactivate.label'))
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading(__('users.reactivate.heading'))
                    ->modalDescription(__('users.reactivate.description'))
                    ->modalSubmitActionLabel(__('users.reactivate.label'))
                    ->visible(fn (User $record): bool => $record->isDeactivated())
                    ->authorize('reactivate')
                    ->successNotificationTitle(__('users.reactivate.done'))
                    ->action(function (Action $action, User $record): void {
                        $record->reactivate();

                        $action->success();
                    }),
                // Shown disabled on an account named on payments or refunds,
                // rather than hidden, so the administrator learns to
                // deactivate it instead (UserPolicy::delete).
                DeleteAction::make()
                    ->hidden(fn (User $record): bool => static::isCurrentUser($record))
                    ->authorizationTooltip(fn (User $record): bool => static::canRemoveAccounts() && $record->hasFinancialRecords())
                    ->authorizationMessage(__('users.delete.has_financial_records'))
                    ->using(fn (User $record): bool => static::deleteUser($record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // Each selected account is asked UserPolicy::delete() on
                    // its own, server side: the current user and anyone named
                    // on financial records drop out of the selection whatever
                    // the request names. Filament's default bulk delete then
                    // deletes through the model one at a time, so a gateway
                    // refusing one account is reported and the rest proceed.
                    DeleteBulkAction::make()
                        ->authorizeIndividualRecords('delete'),
                ]),
            ])
            // The current user's row cannot be selected at all, so even
            // "select all" across pages cannot sweep the account performing
            // the deletion into a bulk delete.
            ->checkIfRecordIsSelectableUsing(
                fn (User $record): bool => ! static::isCurrentUser($record),
            );
    }

    /**
     * One avatar conversion as an absolute URL, or null when there is no
     * uploaded photo to show.
     *
     * Shared by the form entry and the table column. The state is passed
     * through url() because ImageEntry and ImageColumn treat anything that is
     * not already an absolute URL as a path on their own disk: avatarUrl()
     * returns a root-relative "/storage/..." string, which would otherwise be
     * looked up as a FILE of that name, silently fail the existence check,
     * and fall back to initials for every user who has an avatar. Null still
     * falls through to the initials fallback below, as it does while
     * processing is in flight.
     */
    public static function avatarState(User $record, string $conversion = 'thumb'): ?string
    {
        return filled($url = $record->avatarUrl($conversion)) ? url($url) : null;
    }

    /**
     * Build a data-URI initials avatar for a user with no uploaded photo.
     *
     * A facade over User::initialsAvatarUrl(), kept because the table's
     * defaultImageUrl() and the tests speak this name; the markup itself
     * lives on the model so every data-URI render path shares one builder.
     */
    public static function initialsAvatarUrl(User $user): string
    {
        return $user->initialsAvatarUrl();
    }

    /**
     * Describe the role currently chosen in the form.
     *
     * Resolved with tryFrom() and a fallback rather than Role::from(): the
     * state comes from the live form, so it is whatever the browser last
     * sent. An unrecognised value, an empty string from a cleared select, or
     * a non-scalar from a tampered payload would all make from() throw a
     * ValueError -- an unhandled 500 while merely rendering a help line.
     * Falling back to the record's stored role (and then to the default)
     * keeps the copy sensible instead.
     */
    public static function describeRole(mixed $state, ?User $record): string
    {
        $role = is_string($state) ? Role::tryFrom($state) : null;

        // The record's own role stands in while the form holds nothing
        // usable; creation has no record at all, so that falls to the default.
        $role ??= $record instanceof User ? $record->role : Role::DEFAULT;

        return __($role->description());
    }

    /**
     * Determine whether a record is the signed-in user's own account.
     *
     * Null during creation, where there is no record to compare against yet.
     */
    public static function isCurrentUser(?User $record): bool
    {
        return $record !== null && $record->getKey() === auth()->id();
    }

    /**
     * Whether the signed-in user may delete and deactivate accounts at all.
     *
     * The delete and deactivate tooltips explain an integrity refusal to
     * someone who could otherwise act. Staff who may only view users are
     * refused for want of the permission, so for them the actions stay hidden
     * rather than disabled with a reason that is not theirs.
     */
    public static function canRemoveAccounts(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->hasPermission(Permission::DeleteUsers);
    }

    /**
     * Delete one account from the table, telling the administrator if it was kept.
     *
     * User's deleting event refuses when the gateway will not cancel a live
     * subscription, and when the account became undeletable after the row was
     * rendered. Either would otherwise surface as an error page; returning
     * false leaves the account in place with the reason shown.
     */
    public static function deleteUser(User $record): bool
    {
        try {
            return (bool) $record->delete();
        } catch (GatewayException $e) {
            report($e);

            Notification::make()->danger()->title(__('users.delete.gateway_failed'))->send();
        } catch (AccountRemovalRefused $e) {
            Notification::make()->danger()->title($e->protectsLastAdministrator()
                ? __('users.delete.last_administrator')
                : __('users.delete.refused'))->send();
        }

        return false;
    }

    /**
     * Persist the modal form's data onto a user.
     *
     * The role is not mass-assignable (see the User model's Fillable
     * attribute), so it is assigned as a property here -- the same way
     * AdminUserSeeder sets it.
     *
     * Passwords are not part of this form. A new user gets an unguessable
     * random one so the NOT NULL column is satisfied without anybody, the
     * creating admin included, knowing a working credential; the account is
     * reached by way of the "forgot password" flow. An existing user's
     * password is never touched here.
     *
     * @param  array<string, mixed>  $data
     */
    public static function saveUser(User $user, array $data): User
    {
        $user->name = $data['name'];
        $user->email = $data['email'];

        if (! $user->exists) {
            $user->password = Str::password(32);
        }
        // Enforced here as well as on the disabled select: a disabled field is
        // dropped from the payload, so without this an operator could still
        // demote themselves by posting a role directly.
        //
        // An absent or unrecognised role leaves the user's current role
        // alone, and only a brand new user falls back to the default. Reading
        // it as "demote to the default" instead would silently strip an
        // administrator's rights on any payload that happened to omit the
        // field -- a privilege change nobody asked for and nothing reports.
        if (! static::isCurrentUser($user)) {
            $submitted = $data['role'] ?? null;

            $user->role = (is_string($submitted) ? Role::tryFrom($submitted) : null)
                ?? ($user->exists ? $user->role : Role::DEFAULT);
        }

        // In a transaction so the model's last-administrator guard can hold
        // the administrators' rows while it checks: two administrators
        // demoting each other at once must not both succeed.
        DB::transaction(fn (): bool => $user->save());

        return $user;
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageUsers::route('/'),
        ];
    }
}
