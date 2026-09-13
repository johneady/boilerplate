<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Health Check
    |--------------------------------------------------------------------------
    |
    | App\Http\Controllers\HealthController backs the /health endpoint, which
    | exists because Laravel's own /up proves only that PHP booted and the
    | framework can render a response. This stack runs its queue, cache and
    | sessions on the database and its workers in separate containers, so a
    | container can pass /up while the queue is dead and every session is
    | failing to save. /health resolves the dependencies instead of assuming
    | them.
    |
    | /up is left registered and is still what the container HEALTHCHECK and
    | the platform use to decide whether to restart a container: a deep check
    | is the wrong thing to restart on, because a database blip would cycle
    | every web container at once and take the site down harder than the blip
    | did. /health is for monitoring -- an uptime check that pages a human.
    |
    */

    /*
    | Seconds a queue worker may go without completing a job before the queue
    | check reports degraded.
    |
    | Sized against work this application actually schedules rather than a
    | round number: routes/console.php runs app:prune-expired-storage hourly,
    | so on an otherwise idle installation a healthy worker still completes
    | something every hour. The ceiling is generous enough that one missed
    | run is not an alert, and tight enough that a wedged worker is caught
    | within the next.
    */
    'queue_heartbeat_max_age' => (int) env('HEALTH_QUEUE_HEARTBEAT_MAX_AGE', 7200),

    /*
    | Whether the queue check may report degraded at all.
    |
    | An installation running QUEUE_CONNECTION=sync has no worker and never
    | writes a heartbeat, which is a correct configuration rather than a
    | fault; the check skips itself there. This switch is for the other case
    | -- a deployment that runs a worker but does not want the endpoint to go
    | red on it.
    */
    'check_queue' => (bool) env('HEALTH_CHECK_QUEUE', true),

];
