<?php

namespace App\Models;

use App\Media\MediaCollection;
use Carbon\CarbonImmutable;
use Database\Factories\MediaFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Number;

/**
 * One stored file, owned by any model that uses App\Concerns\HasMedia.
 *
 * This is the row that was missing while a file was a string column: an avatar
 * was `users.avatar_path` and the site logo was a settings value, so nothing
 * recorded who uploaded either, nothing could hold two files, and nothing could
 * find a file whose owner had gone. A media row carries its own identity, so
 * the same machinery serves an avatar, a page's gallery and a PDF attachment.
 *
 * Two kinds of row, told apart by `conversions` being null or not:
 *
 * - An IMAGE has a conversion set. `path` is the DIRECTORY holding the written
 *   conversions and `conversions` maps each name to its file. The original is
 *   never stored -- App\Jobs\ProcessUploadedImage re-encodes it and deletes the
 *   upload, which is what strips EXIF and any payload smuggled into a
 *   structurally valid image. See .ai/rules/concerns.md.
 * - A DOCUMENT has no conversion set. `path` is the file itself, stored as
 *   uploaded on a PRIVATE disk and only ever reached through a signed route,
 *   never a public URL.
 *
 * @property int $id
 * @property string|null $model_type
 * @property int|null $model_id
 * @property string $collection
 * @property string|null $conversion_set
 * @property string $disk
 * @property string $path
 * @property string $file_name
 * @property string $mime_type
 * @property int $size
 * @property array<string, string>|null $conversions
 * @property int|null $width
 * @property int|null $height
 * @property int|null $uploaded_by
 * @property int $sort_order
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Media extends Model
{
    /** @use HasFactory<MediaFactory> */
    use HasFactory;

    /**
     * Rows are written by App\Media\MediaManager, never mass-assigned.
     *
     * Every column here is a claim about bytes on disk -- which disk, which
     * path, which size. A request that could set them could point a row at
     * any file the application can read, so the manager is the only writer and
     * there is no $fillable at all, exactly as App\Models\AuditLog does it.
     *
     * @var list<string>
     */
    protected $guarded = ['*'];

    /**
     * URLs already resolved for this instance, keyed by stored path.
     *
     * @var array<string, string|null>
     */
    protected array $resolvedUrls = [];

    /**
     * The table name, since Eloquent would pluralise this to "medias".
     *
     * @var string
     */
    protected $table = 'media';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'conversions' => 'array',
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /**
     * The record this file belongs to.
     *
     * @return MorphTo<Model, $this>
     */
    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Whether the owning record's class still exists in the application.
     *
     * A media row outlives code: a model renamed or removed in a release leaves
     * rows naming a class that no longer autoloads, and merely TOUCHING the
     * relation then throws Error rather than returning null. That is not
     * theoretical -- the library eager-loads `model` for every row, so one such
     * row takes the whole page down with a 500.
     *
     * Checked before the relation is resolved anywhere that must not fail.
     */
    public function hasResolvableOwner(): bool
    {
        if ($this->model_type === null || $this->model_id === null) {
            return false;
        }

        // Morph aliases resolve through the map; an unaliased type is the class
        // name itself. Either way the question is whether it can be loaded.
        $class = Relation::getMorphedModel($this->model_type) ?? $this->model_type;

        return class_exists($class);
    }

    /**
     * The owning record, or null when it cannot be resolved.
     *
     * Use this rather than `$media->model` anywhere a failure would break a
     * page: it returns null for a deleted record AND for one whose class is
     * gone, instead of throwing for the second.
     */
    public function ownerOrNull(): ?Model
    {
        if (! $this->hasResolvableOwner()) {
            return null;
        }

        return $this->model;
    }

    /**
     * The account that uploaded this file.
     *
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Whether this row describes a re-encoded image rather than a stored file.
     *
     * The presence of conversions is the discriminator, not the mime type: a
     * row whose processing job has not run yet has an image mime type and no
     * conversions, and must not be rendered as though a file existed at every
     * conversion path.
     */
    public function isImage(): bool
    {
        return $this->conversions !== null && $this->conversions !== [];
    }

    /**
     * Whether the bytes are reachable without a signed route.
     *
     * Driven by the disk rather than the mime type. A document lives on the
     * private disk and is served by App\Http\Controllers\MediaController; an
     * image's conversions live on the public one.
     */
    public function isPubliclyReadable(): bool
    {
        return $this->disk === $this->publicDisk();
    }

    /**
     * The stored path for one conversion, or the file itself.
     *
     * Reads the recorded conversions map rather than composing a path from the
     * conversion name. A path built by string concatenation claims a file
     * exists at a name nothing ever wrote -- which is what happens the moment a
     * conversion is added to config and the rows predating it are asked for it.
     */
    public function path(?string $conversion = null): ?string
    {
        if ($conversion === null) {
            return $this->path;
        }

        return $this->conversions[$conversion] ?? null;
    }

    /**
     * A URL for this file, or null when there is nothing to link to.
     *
     * Null rather than a broken link in four cases that all really happen: an
     * image whose processing job has not finished, a conversion that did not
     * exist when this row was written, a private document (which has a URL only
     * through the signed route -- see signedUrl()), and a file the row still
     * names but that is no longer on the disk.
     *
     * That last check is not redundant with the conversions map. The map says
     * what processing WROTE; it cannot know what a failed deploy, a manual
     * cleanup or a half-restored backup later removed. Returning a URL for a
     * file that is gone renders a broken image, where null renders the initials
     * or the bundled mark -- which is what every caller is written to expect.
     */
    public function url(?string $conversion = null): ?string
    {
        if (! $this->isPubliclyReadable()) {
            return null;
        }

        $path = $this->path($conversion ?? $this->defaultConversion());

        if ($path === null) {
            return null;
        }

        $storage = Storage::disk($this->disk);

        // Memoised per instance: a page renders the same avatar several times
        // (the sidebar, the user menu, the identity row), and each call would
        // otherwise stat the disk again -- a network round-trip once the image
        // disk points at a remote filesystem.
        if (! array_key_exists($path, $this->resolvedUrls)) {
            $this->resolvedUrls[$path] = $storage->exists($path)
                ? $storage->url($path)
                : null;
        }

        return $this->resolvedUrls[$path];
    }

    /**
     * A time-limited URL for a private file.
     *
     * Signed rather than a plain route: the route authorises through the
     * owning model's policy, but a link pasted into a chat outlives the
     * session that made it. An expiry bounds that.
     */
    public function signedUrl(int $minutes = 5): string
    {
        return URL::temporarySignedRoute(
            'media.show',
            now()->addMinutes($minutes),
            ['media' => $this->getKey()],
        );
    }

    /**
     * The conversion asked for when a caller names none.
     *
     * The collection's own default, so `$page->firstMedia('hero')->url()`
     * renders the size that collection was defined for rather than whichever
     * conversion happens to sort first.
     */
    public function defaultConversion(): ?string
    {
        return MediaCollection::tryFrom($this->collection)?->defaultConversion();
    }

    /**
     * The file size, written the way a person reads it.
     */
    public function humanSize(): string
    {
        return (string) Number::fileSize($this->size, precision: 1);
    }

    /**
     * Delete the bytes this row points at, then the row.
     *
     * Overriding delete() rather than hooking `deleted` is deliberate: a
     * `deleted` hook cannot stop the row going when the files cannot be
     * removed, and a row is the only record that the files exist. Removing the
     * bytes first means a failure leaves a row still naming them, which the
     * orphan prune can retry -- the other order leaks the file permanently.
     */
    public function delete(): ?bool
    {
        $this->deleteFiles();

        return parent::delete();
    }

    /**
     * Remove every file this row owns from its disk.
     *
     * An image owns a DIRECTORY (its conversions, and nothing else is written
     * there -- each upload gets a fresh uuid segment), so the directory goes.
     * A document owns exactly one file.
     */
    public function deleteFiles(): void
    {
        $storage = Storage::disk($this->disk);

        if ($this->isImage()) {
            $storage->deleteDirectory($this->path);

            return;
        }

        $storage->delete($this->path);
    }

    /**
     * Limit the query to one named collection.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function inCollection(Builder $query, MediaCollection|string $collection): void
    {
        $query->where('collection', $collection instanceof MediaCollection ? $collection->value : $collection);
    }

    /**
     * Limit the query to rows whose owning record is gone or was never set.
     *
     * These are what the prune collects: a file uploaded against a form that
     * was abandoned, one whose owner was deleted by a path that did not
     * cascade, or one naming a model class a later release removed. Matching on
     * a null model_id alone would miss the last two.
     *
     * The class check is done in PHP rather than SQL because only the
     * application knows which types still autoload; the database cannot. The
     * list of distinct types is tiny (one per model that holds files), so this
     * is a single extra query, not a scan.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function orphaned(Builder $query): void
    {
        self::scopeToOrphaned($query);
    }

    /**
     * Narrow any query to orphaned rows.
     *
     * The scope above is the idiomatic form, but a #[Scope] is invisible to
     * static analysis on a generic Builder -- which is exactly what a Filament
     * filter receives. This gives callers there one function to delegate to,
     * instead of restating the condition and drifting from it.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public static function scopeToOrphaned(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->whereNull('model_id')
                ->orWhereNull('model_type')
                ->orWhereIn('model_type', self::unresolvableTypes());
        });
    }

    /**
     * Stored morph types whose class no longer exists.
     *
     * @return list<string>
     */
    public static function unresolvableTypes(): array
    {
        /** @var list<string> $types */
        $types = self::query()
            ->whereNotNull('model_type')
            ->distinct()
            ->pluck('model_type')
            ->all();

        return array_values(array_filter(
            $types,
            fn (string $type): bool => ! class_exists(Relation::getMorphedModel($type) ?? $type),
        ));
    }

    /**
     * The disk processed, publicly readable images are written to.
     */
    private function publicDisk(): string
    {
        /** @var string $disk */
        $disk = config('images.disk');

        return $disk;
    }
}
