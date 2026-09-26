---
paths:
  - 'app/Notifications/**'
---

# App Notifications

## Extend BaseNotification; queueing is opt-in, not the default
`make:notification` emits a standalone class that re-decides channels, branding and subject wording each time. Extend App\Notifications\BaseNotification instead: it supplies via() => ['mail'], businessName() (the BusinessName SETTING, never config('app.name') — same rule as the views and published mail templates), and mailMessage() which suffixes the subject with the business name so an alert is identifiable in an inbox receiving them from several installations.

It is named BaseNotification, not Notification, because App\Notifications\Notification would shadow Illuminate's own class inside this namespace and force every subclass to disambiguate.

BaseNotification is deliberately NOT ShouldQueue, which is the opposite of App\Jobs\Job. Operator alerts must not depend on the queue: QueueJobFailed announces that the queue just failed, and handing that alert to the same queue leaves it unsent in the table it is warning about. ContactSubmissionReceived IS queued: the submission is persisted before dispatch, so a failed send loses nothing and the failed-job alert reports it (see .ai/rules/models-notifications.md). A notification that SHOULD be queued declares `implements ShouldQueue` on itself — PasswordChanged is the worked example.

businessName() resolves per call rather than in a constructor: a queued notification constructed in one process and rendered in another would otherwise serialise a stale name and mail it after the setting changed.

PasswordChanged fires from a User::updated hook in AppServiceProvider::configurePasswordChangeAlerts(), not from the two call sites that change a password (Settings\Security::updatePassword and Actions\Fortify\ResetUserPassword). A third path would otherwise have to remember, and the once it is forgotten is the once it mattered. Use wasChanged('password'), NOT isDirty() — inside `updated` the attributes are already synced, so isDirty() is always false and the alert would never fire. Both directions are covered by tests/Feature/PasswordChangedNotificationTest.php.

Security alerts carry no link and no token, deliberately: a warning that invites a click trains the reflex phishing depends on. A test asserts actionUrl is null.
