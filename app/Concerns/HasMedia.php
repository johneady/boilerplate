<?php

namespace App\Concerns;

use App\Media\MediaCollection;
use App\Models\Media;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Gives a model named collections of stored files.
 *
 * Opt-in per model, like App\Concerns\Auditable: a model holds files because
 * somebody decided it should, not because every model can.
 *
 * Pair it with the App\Media\HoldsMedia interface on the model, so a type hint
 * can require a media-owning model rather than a bare Model.
 *
 * The trait only READS and detaches. Attaching goes through
 * App\Media\MediaManager, which is the single place that validates an upload,
 * stages it on a private disk and decides whether it is re-encoded or stored --
 * so there is no way to attach a file that skipped those steps.
 */
trait HasMedia
{
    /**
     * Delete a record's files along with the record.
     *
     * Without this the rows survive their owner as orphans pointing at bytes
     * nothing will ever serve, and the disk grows forever. A database cascade
     * cannot do it: the FILES are not in the database, so the rows have to be
     * deleted through the model, one at a time, to remove what each points at.
     */
    public static function bootHasMedia(): void
    {
        static::deleting(function (self $model): void {
            // A soft-deleting model is not gone, and its files must come back
            // with it if it is restored.
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                return;
            }

            $model->media()->each(fn (Media $media) => $media->delete());
        });
    }

    /**
     * Every file attached to this record.
     *
     * @return MorphMany<Media, $this>
     */
    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'model')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * The files in one collection.
     *
     * @return Collection<int, Media>
     */
    public function getMedia(MediaCollection $collection): Collection
    {
        // Filtered in memory when the relation is already loaded, so rendering
        // three collections on one page is one query rather than four. An
        // eager-loaded ->media has every collection in it already.
        if ($this->relationLoaded('media')) {
            return $this->media
                ->where('collection', $collection->value)
                ->values();
        }

        return $this->media()->inCollection($collection)->get();
    }

    /**
     * The first file in a collection, if it has one.
     */
    public function firstMedia(MediaCollection $collection): ?Media
    {
        return $this->getMedia($collection)->first();
    }

    /**
     * A URL for the first file in a collection.
     *
     * Null when the collection is empty, when the file is private, and when the
     * processing job has not written the conversions yet -- so a caller renders
     * a placeholder rather than a broken image in all three cases. That is why
     * this returns null instead of throwing: an avatar being a few seconds
     * behind is normal, not exceptional.
     */
    public function mediaUrl(MediaCollection $collection, ?string $conversion = null): ?string
    {
        return $this->firstMedia($collection)?->url($conversion);
    }

    /**
     * Whether this record has at least one file in a collection.
     */
    public function hasMedia(MediaCollection $collection): bool
    {
        if ($this->relationLoaded('media')) {
            return $this->media->contains('collection', $collection->value);
        }

        return $this->media()->inCollection($collection)->exists();
    }

    /**
     * Remove every file in one collection.
     *
     * Deleted one model at a time rather than through a mass delete, because
     * Media::delete() is what removes the bytes -- a query-builder delete would
     * drop the rows and leave the files behind with nothing pointing at them.
     */
    public function clearMedia(MediaCollection $collection): void
    {
        $this->getMedia($collection)->each(fn (Media $media) => $media->delete());

        // The in-memory relation still holds the rows that were just deleted,
        // so a caller that renders after clearing would show them.
        $this->unsetRelation('media');
    }
}
