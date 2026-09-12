---
paths:
  - 'resources/views/vendor/mail/**, resources/views/vendor/notifications/**'
---

# Notifications

## Mail templates brand from $businessName
Outgoing email brands from the BusinessName setting, not config('app.name') -- the same rule as .ai/rules/views.md. Four published vendor templates make that true: vendor/mail/html/message.blade.php (header + footer), vendor/mail/text/message.blade.php, vendor/mail/html/layout.blade.php (the <title>), and vendor/notifications/email.blade.php (the "Regards," salutation). $businessName reaches all of them through the View::composer('*') in AppServiceProvider.

Note on how mail:: resolves: Illuminate\Mail\Markdown::render() calls replaceNamespace('mail', ...), so the mail:: paths come from config('mail.markdown.paths') rather than Blade's ordinary vendor-override lookup. That needs NO entry in this project's config/mail.php: the framework's own config/mail.php (merged with the app's) already lists resource_path('views/vendor/mail'), so publishing into that directory is enough. Do not re-add a markdown key just to "register" the path -- an app-level copy also drops the MAIL_MARKDOWN_THEME env support the framework default provides.

Only the templates that needed the brand swap are published, not the whole mail theme, so the header, footer, button and CSS still track the framework -- recheck these four on a framework upgrade.

tests/Feature/PreviewMailsTest.php renders all four email types through app:preview-mails and asserts the business name appears and APP_NAME does not.
