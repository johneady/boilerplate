<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property CarbonImmutable|null $email_verified_at
 * @property string|null $avatar_path
 * @property bool $is_admin
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property CarbonImmutable|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasAvatar, MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Avatar URLs already resolved for this instance, keyed by path|conversion.
     *
     * @var array<string, string|null>
     */
    protected array $resolvedAvatarUrls = [];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_admin' => 'boolean',
            'password' => 'hashed',
        ];
    }

    /**
     * Determine whether the user may access a Filament panel.
     *
     * The panel has no login page of its own, so this is purely an
     * authorization check on an already-authenticated Fortify session.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return (bool) $this->is_admin;
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }

    /**
     * Get the URL of one of the user's avatar conversions.
     *
     * Null until App\Jobs\ProcessUploadedImage has written the conversions,
     * which is what lets the UI fall back to initials during the short window
     * between upload and processing -- the unprocessed original is never
     * served, because it has not been stripped of metadata yet.
     */
    public function avatarUrl(string $conversion = 'thumb'): ?string
    {
        if ($this->avatar_path === null) {
            return null;
        }

        // The sidebar and the user menu each render an avatar, so this is
        // called several times per request. Without memoisation each call
        // stats the disk again -- a network round-trip once IMAGE_DISK is
        // pointed at a remote filesystem.
        $cacheKey = $this->avatar_path.'|'.$conversion;

        if (array_key_exists($cacheKey, $this->resolvedAvatarUrls)) {
            return $this->resolvedAvatarUrls[$cacheKey];
        }

        /** @var string $disk */
        $disk = config('images.disk');

        /** @var string $format */
        $format = config('images.format');

        $path = $this->avatar_path.'/'.$conversion.'.'.$format;

        $storage = Storage::disk($disk);

        // A conversion can be missing if the set was written by an older
        // configuration; falling back to initials beats a broken image.
        return $this->resolvedAvatarUrls[$cacheKey] = $storage->exists($path)
            ? $storage->url($path)
            : null;
    }

    /**
     * Get the avatar URL shown in the Filament admin panel.
     *
     * Filament resolves this through its HasAvatar contract; returning null
     * lets the panel fall back to its own initials-based default.
     */
    public function getFilamentAvatarUrl(): ?string
    {
        return $this->avatarUrl();
    }
}
