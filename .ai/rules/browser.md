---
paths:
  - 'tests/Browser/**'
---

# Browser

## Pest browser tests: no file uploads, no WebAuthn, and Playwright must match the cached browser
tests/Browser is bound to TestCase + RefreshDatabase in tests/Pest.php and declared as its own <testsuite> in phpunit.xml. Without both, browser tests fail with "Target class [config] does not exist".

File uploads do NOT work. The plugin's in-process server builds its Symfony request with `[], // @TODO files...` (Drivers/LaravelHttpServer.php), so every uploaded file is dropped and Livewire then throws "Undefined array key 0" in WithFileUploads::_finishUpload(). Verified the same avatar upload succeeds in real Chrome against `php artisan serve` -- so a failure here is the harness, not the app. Do not add browser coverage for uploads until the plugin implements it.

WebAuthn/passkeys cannot be driven either: the plugin exposes no CDP session, so there is no way to install a virtual authenticator. tests/Feature/Auth/PasskeyLoginOptionsTest.php covers the server half (the challenge payload shape and rpId) instead.

Playwright's npm version must match a browser build in ~/.cache/ms-playwright, since the CDN download can be blocked. playwright 1.62.0 -> chromium-1234, 1.61.0 -> chromium-1228. Pinned to 1.62.0 in package.json for that reason.

Assert paths with assertPathIs(), never assertUrlIs() -- the test server binds an ephemeral port.
