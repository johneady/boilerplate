---
paths:
  - 'app/Jobs/ProcessUploadedImage.php, app/Concerns/ImageValidationRules.php, config/images.php'
---

# Concerns

## User images are re-encoded, never served as uploaded
Uploads are staged on the `local` (private) disk and handed to ProcessUploadedImage; only the re-encoded conversions reach the `public` disk. Re-encoding is a security control, not just resizing: decoding to a raster and writing a fresh file discards EXIF (GPS on phone photos) and anything smuggled into a structurally valid image. Never serve the staged original, and never "optimise" by copying it straight to the public disk.

Validation uses `mimes:` against config('images.accepted_extensions'), NOT Laravel's `image` rule. Verified on Laravel 13: `image` rejects SVG by default but accepts it again under `image:allow_svg`. An explicit allow-list does not depend on that default holding, and it keeps accepted formats identical to what the job can decode. SVG must stay off the list — it is a scriptable document served from our own origin.

The processed set is attached to an App\Models\Media row, not to a column — `users.avatar_path` and the Logo setting string are both gone. See .ai/rules/concerns-models.md.

The job decodes the source ONCE and deep-clones per conversion: Intervention modifiers mutate in place (a modifier replaces each frame's native and returns the same instance), so the conversions cannot share one instance -- `clone` is safe because both drivers' Image implement `__clone` as a deep copy (Gd re-copies each frame's GdImage; Imagick clones its Imagick object). Do not collapse the clones into a shared instance. The job also rolls back already-written files if a later conversion throws, so a retry never finds a half-written set. Add new sizes as a key under config('images.conversions'), not as a new job.
