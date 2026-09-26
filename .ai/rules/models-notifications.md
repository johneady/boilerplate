---
paths:
  - 'app/Livewire/Contact.php, app/Models/ContactSubmission.php, app/Notifications/ContactSubmissionReceived.php'
---

# Models Notifications

## Contact submissions are stored before they are emailed
`Contact::submit()` writes the row FIRST, then attempts the notification inside a try/catch that logs and swallows. The mailer's unsaved default is 'log' (SettingKey::MailMailer), so on a fresh instance nothing is delivered anywhere -- an email-only contact form would silently discard every message until somebody configured SMTP. Do not reorder these, and do not let a dispatch failure propagate: the visitor already completed the form.

A blank BusinessEmail sends nothing at all. That setting's own help text says blank hides the address, so blank must not mean "mail it anyway".

The spam checks (honeypot `website` field, minimum fill time) report SUCCESS without storing, and each rejection counts against its own per-address budget (`contact-form-rejections:`), cut off after enough of them. Telling a bot which check caught it is how the next version gets past both.

Timing uses `now()`, never native `time()`: travel() in a test moves Carbon's clock and not PHP's, so a native time() here is untestable -- every test submitting instantly trips the minimum-fill check and silently stores nothing. That cost a debugging round.

The notification IS queued (an explicit per-class opt-in that BaseNotification documents), so submit() dispatches rather than sends; a failure inside the queue is reported by the failed-job alert. It stays registered in PreviewableEmails::all(), which PreviewMailsTest renders. Those tests derive their count from the catalogue -- do not reintroduce a literal.
