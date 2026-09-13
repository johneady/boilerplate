<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * A user as the API represents it.
 *
 * The worked example for this API's resources, and the shape to copy: fields
 * are listed explicitly rather than spread from the model, so a column added
 * later -- an internal flag, a token, a billing reference -- is not published
 * the moment it is migrated. Everything the users table holds besides the
 * fields below (the password hash, remember token, and the two-factor secret
 * and recovery codes) is excluded for exactly that reason.
 *
 * Note there is also an App\Filament\Resources\Users\UserResource. The names
 * match but the concepts do not: that one describes an admin panel screen,
 * this one an API representation. Import the right namespace.
 *
 * @mixin User
 */
class UserResource extends BaseApiResource
{
    /**
     * Whether to resolve and publish the user's avatar URL.
     */
    protected bool $withAvatarUrl = false;

    /**
     * Include the avatar URL in this resource's payload.
     *
     * Off by default because resolving it touches the filesystem: worth it for
     * a single user, wasteful for a page of them. Call it on the endpoints
     * that actually render an avatar.
     */
    public function withAvatarUrl(bool $withAvatarUrl = true): static
    {
        $this->withAvatarUrl = $withAvatarUrl;

        return $this;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role->value,
            // Resolving an avatar URL stats the images disk, and the model's
            // memoisation is per instance -- so in a collection that is one
            // stat per user, which becomes one network round-trip per user the
            // moment IMAGE_DISK points at S3. Opted into per endpoint with
            // ->withAvatarUrl() rather than paid for by every listing.
            //
            // A conditional key rather than mergeWhen(), which would introduce
            // an integer key and make this method's array shape a lie.
            'avatar_url' => $this->when(
                $this->withAvatarUrl,
                fn (): ?string => $this->getFilamentAvatarUrl(),
            ),
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
