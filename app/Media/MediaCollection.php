<?php

namespace App\Media;

/**
 * Every named slot a model may store files in.
 *
 * A collection is declared here rather than passed as a loose string at the
 * call site, for the same reason App\Auth\Permission and App\Settings\SettingKey
 * are enums: a typo in a string collection name is not an error, it is an empty
 * result. `$user->media('avatars')` against rows written as 'avatar' renders no
 * avatar and reports nothing, and the mistake survives every test that uses the
 * same typo in both places.
 *
 * Declaring the slot also declares its RULES -- single or many, which conversion
 * set applies, whether it is public -- so the answer to "may this collection
 * hold a second file" lives with the collection instead of at each of the
 * places that attach one.
 *
 * Adding a case means classifying it in every match below; there is no default
 * arm on purpose, so a new collection cannot silently inherit avatar behaviour.
 */
enum MediaCollection: string
{
    case Avatar = 'avatar';

    case Logo = 'logo';

    case PageImage = 'page-image';

    case Attachment = 'attachment';

    /**
     * The conversion set from config('images.conversions') for this collection.
     *
     * Null means the file is NOT an image and is stored as uploaded. That is
     * the discriminator the whole pipeline turns on: a null set sends the
     * upload to the private disk and skips App\Jobs\ProcessUploadedImage
     * entirely, because there is nothing to re-encode and re-encoding is the
     * only reason a file may be served publicly.
     */
    public function conversionSet(): ?string
    {
        return match ($this) {
            self::Avatar => 'avatar',
            self::Logo => 'logo',
            self::PageImage => 'page-image',
            self::Attachment => null,
        };
    }

    /**
     * Whether this collection holds at most one file.
     *
     * A single collection replaces on attach rather than accumulating, which is
     * what an avatar and a site logo both mean by "upload a new one". Without
     * this the second upload would leave the first attached and rendering would
     * pick whichever sorted first.
     */
    public function isSingle(): bool
    {
        return match ($this) {
            self::Avatar, self::Logo => true,
            // A page may carry several images -- a hero and whatever the body
            // references -- so this one accumulates.
            self::PageImage, self::Attachment => false,
        };
    }

    /**
     * The conversion rendered when a caller names none.
     *
     * Null for a collection of non-images, which have no conversions at all.
     */
    public function defaultConversion(): ?string
    {
        return match ($this) {
            self::Avatar => 'thumb',
            self::Logo => 'mark',
            self::PageImage => 'wide',
            self::Attachment => null,
        };
    }

    /**
     * The directory on the target disk that this collection's files live under.
     *
     * A fixed prefix per collection, which the manager appends a uuid to. It is
     * never built from user input: a file name is attacker-controlled text, and
     * a path assembled from one is a directory traversal.
     */
    public function directory(): string
    {
        return match ($this) {
            self::Avatar => 'avatars',
            self::Logo => 'logo',
            self::PageImage => 'page-images',
            self::Attachment => 'attachments',
        };
    }

    /**
     * Whether files in this collection are reachable without a signed link.
     *
     * Images are public because they are re-encoded before anything is written:
     * the bytes served are ones this application produced. A document is served
     * as uploaded, so it stays on the private disk and goes out through the
     * signed route, where the owning model's policy is consulted.
     */
    public function isPublic(): bool
    {
        return $this->conversionSet() !== null;
    }

    /**
     * Whether rows in this collection belong to the installation, not a record.
     *
     * An ownerless-by-design row is PERMANENTLY ownerless: it is not a form
     * upload waiting for its record to be saved, and app:prune-orphaned-media
     * must never collect it no matter how old it is. Everything else may pass
     * through an ownerless window (a create form holding a file before the
     * record exists), which the prune's grace period exists for.
     */
    public function isOwnerlessByDesign(): bool
    {
        return match ($this) {
            self::Logo => true,
            self::Avatar, self::PageImage, self::Attachment => false,
        };
    }

    /**
     * The disk files in this collection are written to.
     */
    public function disk(): string
    {
        if (! $this->isPublic()) {
            return 'local';
        }

        /** @var string $disk */
        $disk = config('images.disk');

        return $disk;
    }
}
