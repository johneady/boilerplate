---
paths:
  - 'app/Models/Page.php, resources/views/pages/**, app/Filament/Resources/Pages/**'
---

# Resources Pages

## Page bodies are Markdown with HTML escaped, never rendered HTML
`Page::renderedBody()` is the only place a page body becomes HTML, and its two `Str::markdown()` options are load-bearing:
- `html_input => 'escape'` -- the body is echoed with `{!! !!}`, so without this a stored `<script>` executes for every visitor and the panel's page editor becomes stored XSS.
- `allow_unsafe_links => false` -- drops `javascript:` and `data:` URLs, the same attack as a Markdown link.

This is why the field is a MarkdownEditor and not a RichEditor. Nothing else in this application renders unescaped administrator input; do not make this the first. Swapping in RichEditor means storing HTML and needs a sanitiser instead.

Contact submissions are the stricter case: a stranger wrote them, so ContactSubmissionResource shows the message through TextEntry (escaped, no Markdown), and ContactSubmissionReceived escapes it with `e()` itself -- the quoted body is passed through HtmlString to keep its line breaks, which opts it out of the mail template's escaping.

Tests: PageTest.php asserts the escaping; ContactFormTest.php asserts the notification's.
