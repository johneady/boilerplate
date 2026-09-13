---
paths:
  - app/Http/Controllers/HealthController.php
  - app/Listeners/RecordWorkerHeartbeat.php
  - config/health.php
  - docker-compose.dokploy.yml
---

# Controllers Listeners

## /health is for monitoring; /up is what containers restart on
Both endpoints exist on purpose. `/up` (bootstrap/app.php) proves only that the framework booted, and it stays the container HEALTHCHECK: a deep check there means one database blip cycles every web container at once, taking the site down harder than the blip did. `/health` resolves the database, cache and queue-worker heartbeat, returns 503 when degraded, and is for an external uptime monitor that pages a human but cannot restart anything. Do not point the container HEALTHCHECK at /health.

The queue check needs a heartbeat the container healthchecks cannot produce: those ask supervisorctl whether the worker process is RUNNING, which a worker wedged on a dead database connection still is. App\Listeners\RecordWorkerHeartbeat stamps a cache key on JobProcessed (completion, not pickup) and is registered by listener auto-discovery from its event typehint — same route as SendQueueFailureAlert, which is why neither appears in a provider.

Three queue states are distinguished deliberately: `skipped` (QUEUE_CONNECTION=sync, or switched off — no worker is expected), `unknown` (no heartbeat ever recorded, i.e. a fresh deploy; failing here would turn every deploy red for its first hour) and `failing` (a heartbeat exists but is stale — the wedged-worker signal). The staleness ceiling is sized against routes/console.php running app:prune-expired-storage hourly, so an idle installation still beats regularly.

The response body names the failing check but never why: the route is unauthenticated and an exception message carries the database host, user and paths.

`health` is in Page::RESERVED_SLUGS — routes/web.php ends in a page-serving fallback, so an unreserved slug would let a content page shadow the endpoint.

Testing trap: a test that mocks Cache::put/get but NOT Cache::forget passes even with the write-back comparison deleted, because the unmocked forget() throws BadMethodCallException into the controller's catch block. Mock all three so the comparison is the only thing that can fail.
