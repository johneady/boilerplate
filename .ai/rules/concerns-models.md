---
paths:
  - 'app/Media/**, app/Concerns/HasMedia.php, app/Models/Media.php, config/media.php'
---

# Concerns Models

## Media is one polymorphic model; conversions being null is the discriminator
Every stored file is an App\Models\Media row. Collections are declared as MediaCollection enum cases (never loose strings) — each `match` has no default arm, so a new case must be classified in conversionSet(), isSingle(), defaultConversion() and directory().

`conversions` null vs non-null is what the whole pipeline turns on. Non-null = a re-encoded IMAGE: `path` is the DIRECTORY of written conversions, served publicly because the bytes are ones we produced. Null = either a DOCUMENT stored as uploaded (private disk, signed route only) or an image whose job has not run yet. Never infer the kind from mime_type: an in-flight image has an image mime and no conversions, and rendering it would link files nothing wrote.

App\Media\MediaManager is the ONLY writer — it validates (via rulesFor(), so a call site that forgot cannot store an unchecked file), stages on the private disk, and decides re-encode vs store. Media has no $fillable at all, like AuditLog.

The Logo collection is OWNERLESS (it belongs to the installation, not a record), so replaceSingle() must match on a NULL owner explicitly — querying the collection alone would sweep in every record's files and delete them.

HoldsMedia is deliberately a MARKER interface. Do not redeclare media(), getKey(), getMorphClass() or unsetRelation() on it: Eloquent's versions carry no return types, so declaring one fatals at autoload ("Declaration of Model::getKey() must be compatible"), and media()'s MorphMany<Media, $this> differs per model. Type-hint `Model&HoldsMedia` instead.
