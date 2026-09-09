<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\User;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
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
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                // An admin editing themselves cannot drop their own rights:
                // doing so would lock them out of this panel on save, with no
                // way back in short of re-seeding or another admin's help.
                Toggle::make('is_admin')
                    ->label('Administrator')
                    ->disabled(fn (?User $record): bool => static::isCurrentUser($record))
                    ->helperText(fn (?User $record): string => static::isCurrentUser($record)
                        ? 'You cannot remove your own administrator rights.'
                        : 'Administrators may sign in to this admin panel.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                IconColumn::make('is_admin')
                    ->label('Administrator')
                    ->boolean()
                    ->sortable(),
                IconColumn::make('email_verified_at')
                    ->label('Verified')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Registered')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                TernaryFilter::make('is_admin')
                    ->label('Administrator'),
                TernaryFilter::make('email_verified_at')
                    ->label('Email verified')
                    ->nullable(),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(fn (User $record, array $data): User => static::saveUser($record, $data)),
                DeleteAction::make()
                    ->hidden(fn (User $record): bool => static::isCurrentUser($record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // The current user is filtered out of the selection server
                    // side, not merely made unselectable in the UI, so a forged
                    // request naming their own ID still cannot delete them.
                    DeleteBulkAction::make()
                        ->action(fn (Collection $records) => $records
                            ->reject(fn (Model $record): bool => $record instanceof User
                                && static::isCurrentUser($record))
                            ->each->delete()),
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
     * Determine whether a record is the signed-in user's own account.
     *
     * Null during creation, where there is no record to compare against yet.
     */
    public static function isCurrentUser(?User $record): bool
    {
        return $record !== null && $record->getKey() === auth()->id();
    }

    /**
     * Persist the modal form's data onto a user.
     *
     * is_admin is not mass-assignable (see the User model's Fillable
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
        // Enforced here as well as on the disabled toggle: a disabled field is
        // dropped from the payload, so without this an operator could still
        // demote themselves by posting is_admin directly.
        $user->is_admin = static::isCurrentUser($user)
            ? true
            : (bool) ($data['is_admin'] ?? false);

        $user->save();

        return $user;
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageUsers::route('/'),
        ];
    }
}
