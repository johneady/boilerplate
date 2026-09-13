<?php

namespace App\Media;

/**
 * A model that can own files.
 *
 * Implemented by App\Concerns\HasMedia, which supplies every method. The
 * interface exists so a type hint can require a media-owning model rather than
 * a bare Model -- App\Media\MediaManager attaches files to `$owner`, and
 * without this that is an assumption the type system cannot check, which holds
 * only until somebody passes a model without the trait.
 *
 * Declare it alongside the trait: `class Page extends Model implements HoldsMedia`.
 *
 * It is deliberately a MARKER: media() and the rest are not redeclared here.
 *
 * - Eloquent's own methods (getKey(), getMorphClass(), unsetRelation()) carry
 *   no return types, and an interface that declares one fatals at autoload
 *   time for every model implementing it.
 * - media() returns MorphMany<Media, $this>, whose second parameter differs per
 *   model, so pinning it here forces the interface to be generic and every
 *   implementer and call site to restate the type.
 *
 * Callers type-hint `Model&HoldsMedia`, which asks for the Eloquent surface and
 * the media surface together without restating either.
 */
interface HoldsMedia
{
    /**
     * Remove every file in one collection.
     */
    public function clearMedia(MediaCollection $collection): void;
}
