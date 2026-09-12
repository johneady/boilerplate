---
paths:
  - 'app/Settings/Settings.php, resources/views/partials/head.blade.php'
---

# Partials

## JSON-LD is composed as an array and encoded in the head
Settings::organizationSchema() returns an ARRAY, never rendered JSON. The head hands it to json_encode() with JSON_HEX_TAG|JSON_HEX_AMP so a quote or `</script>` in a stored business name cannot close the script element early. Building the JSON in Blade would make a malformed block possible, which is worse than none — a search engine may discard the page's other signals with it.

Optional business details are omitted when blank rather than emitted empty: a blank telephone is a worse claim than no telephone.

The block is gated on AllowSearchIndexing, like the noindex meta tag — a noindex page gains nothing from structured data. tests/Feature/SitemapAndRobotsTest.php covers the escaping and the omissions.
