---
paths:
  - app/Filament/Resources/Pages/PageResource.php
---

# Filament Resources Pages

## Page body images deliberately skip the media library
The MarkdownEditor's own attachFiles button is the ONLY way to add a page body image, and that is a deliberate decision — not an oversight to be "fixed" by routing it through MediaManager.

Filament stores editor attachments itself and only ever publicly (the docs are explicit that temporary URLs are unsupported for static content), so there is no hook to hand them to ProcessUploadedImage. Accepted cost: body images are served EXACTLY AS UPLOADED — no re-encoding, so EXIF including GPS survives into a public URL — and no media row describes them, so app:prune-orphaned-media cannot collect one after the body stops referencing it.

Because nothing else guards this path, fileAttachmentsDirectory('page-body'), fileAttachmentsAcceptedFileTypes() (SVG excluded — scriptable document on our own origin) and fileAttachmentsMaxSize() are load-bearing, and each has a test in tests/Feature/PageImageTest.php.

Do NOT call fileAttachments(false): it strips attachFiles from the toolbar and removes in-editor upload entirely. A separate "Add image" table action that did go through MediaManager was built and then removed — two ways in, with the safe one in the place nobody looks, was worse than one.

Body image URLs must be root-relative (/storage/...), never absolute: an absolute one bakes in the dev host and breaks everywhere else.
