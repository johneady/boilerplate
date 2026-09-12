---
paths:
  - app/Listeners/SendQueueFailureAlert.php
---

# Listeners

## Queue failure alerting must never throw or flood
Emails the "Failure alert address" (SettingKey::OpsAlertEmail) when a job exhausts its retries. Three constraints, each load-bearing:

The whole listener is wrapped in try/catch. Settings, the throttle cache and the queue are ALL on the database connection here, so a job that failed because the DB went away fails this listener too — and an exception thrown from a Queue::failing listener runs inside the worker's own failure handling, where it can stop the failed_jobs row being written and lose the original error. Alerting is strictly additive; on any error it logs and returns. App\Jobs\Job::failed() still logs without touching the DB.

QueueJobFailed deliberately does NOT implement ShouldQueue. Queueing an alert about a broken queue leaves it unsent in the table it is warning about. It sends inline.

Throttled to one alert per job class per config('queue.failure_alert_throttle_minutes') via atomic Cache::add(). Queue failures are correlated — an expired credential fails the whole backlog in seconds — and hundreds of identical emails get an alert inbox muted. Keyed per job class so a distinct failure in the same burst still gets through.

The notification carries no job payload (unencrypted mail, payload holds model attributes/tokens). tests/Feature/QueueFailureAlertTest.php covers all of it; each guard was mutation-checked.
