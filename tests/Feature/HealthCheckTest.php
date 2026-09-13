<?php

use App\Listeners\RecordWorkerHeartbeat;
use App\Models\Page;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

test('health reports ok when every dependency answers', function () {
    $response = $this->getJson(route('health'));

    $response->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('checks.database.status', 'ok')
        ->assertJsonPath('checks.cache.status', 'ok');
});

test('health returns 503 when the database is unreachable', function () {
    // The point of the endpoint over /up: the framework boots fine, so /up is
    // still green while the database this stack runs its queue, cache and
    // sessions on has gone away.
    DB::shouldReceive('connection->select')
        ->andThrow(new RuntimeException('SQLSTATE[HY000] [2002] Connection refused'));

    $response = $this->getJson(route('health'));

    $response->assertServiceUnavailable()
        ->assertJsonPath('status', 'degraded')
        ->assertJsonPath('checks.database.status', 'failing');
});

test('health reports the cache failing when a write does not come back', function () {
    // A store that silently discards writes, which is how the database cache
    // store behaves against a missing table: the read returns null, which is
    // indistinguishable from a miss unless the check wrote the value first.
    // forget() is mocked alongside the other two deliberately: an unmocked
    // call throws BadMethodCallException, which the controller catches and
    // reports as failing -- so the test would pass with the write-back
    // comparison deleted. Mocking it leaves the comparison as the only thing
    // that can produce the failure.
    Cache::shouldReceive('put')->andReturnTrue();
    Cache::shouldReceive('get')->andReturnNull();
    Cache::shouldReceive('forget')->andReturnTrue();

    $response = $this->getJson(route('health'));

    $response->assertServiceUnavailable()
        ->assertJsonPath('checks.cache.status', 'failing');
});

test('health does not leak the reason a dependency failed', function () {
    // The route is unauthenticated, and an exception message carries the
    // database host, the username and the file path.
    DB::shouldReceive('connection->select')
        ->andThrow(new RuntimeException('SQLSTATE[HY000] [1045] Access denied for user "deploy"@"10.0.0.4"'));

    $response = $this->getJson(route('health'));

    $response->assertDontSee('deploy')
        ->assertDontSee('10.0.0.4')
        ->assertDontSee('SQLSTATE');
});

test('the queue check is skipped when jobs run inline', function () {
    config()->set('queue.default', 'sync');

    $response = $this->getJson(route('health'));

    $response->assertOk()
        ->assertJsonPath('checks.queue.status', 'skipped');
});

test('the queue check is skipped when it is switched off', function () {
    config()->set('queue.default', 'database');
    config()->set('health.check_queue', false);

    $response = $this->getJson(route('health'));

    $response->assertOk()
        ->assertJsonPath('checks.queue.status', 'skipped');
});

test('a queue with no heartbeat yet is unknown rather than failing', function () {
    // A freshly deployed instance whose worker has not had a job to run.
    // Failing here would turn every deploy red for its first hour.
    config()->set('queue.default', 'database');
    Cache::forget(RecordWorkerHeartbeat::CACHE_KEY);

    $response = $this->getJson(route('health'));

    $response->assertOk()
        ->assertJsonPath('checks.queue.status', 'unknown');
});

test('a recent heartbeat reports the queue as ok', function () {
    config()->set('queue.default', 'database');
    config()->set('health.queue_heartbeat_max_age', 7200);
    Cache::put(RecordWorkerHeartbeat::CACHE_KEY, now()->subMinutes(5)->getTimestamp());

    $response = $this->getJson(route('health'));

    $response->assertOk()
        ->assertJsonPath('checks.queue.status', 'ok');
});

test('a stale heartbeat fails the health check', function () {
    // The wedged-worker signal, which supervisorctl cannot produce: a worker
    // holding a dead database connection stays RUNNING while nothing moves.
    config()->set('queue.default', 'database');
    config()->set('health.queue_heartbeat_max_age', 7200);
    Cache::put(RecordWorkerHeartbeat::CACHE_KEY, now()->subHours(3)->getTimestamp());

    $response = $this->getJson(route('health'));

    $response->assertServiceUnavailable()
        ->assertJsonPath('status', 'degraded')
        ->assertJsonPath('checks.queue.status', 'failing');
});

test('a completed job advances the worker heartbeat', function () {
    Cache::forget(RecordWorkerHeartbeat::CACHE_KEY);

    $event = new JobProcessed('database', $this->mock(Job::class));

    (new RecordWorkerHeartbeat)->handle($event);

    expect(Cache::get(RecordWorkerHeartbeat::CACHE_KEY))
        ->toBeNumeric()
        ->and((int) Cache::get(RecordWorkerHeartbeat::CACHE_KEY))
        ->toBe(now()->getTimestamp());
});

test('a cache store that throws does not break the worker loop', function () {
    // The listener runs inside the worker's own post-job handling, so an
    // exception here would turn a successfully processed job into a failure.
    Cache::shouldReceive('put')->andThrow(new RuntimeException('cache table is gone'));

    $event = new JobProcessed('database', $this->mock(Job::class));

    (new RecordWorkerHeartbeat)->handle($event);
})->throwsNoExceptions();

test('a page cannot claim the health slug', function () {
    // The web routes end in a fallback that serves content pages, so a page
    // saved at this slug would shadow the endpoint were it not reserved.
    expect(Page::RESERVED_SLUGS)->toContain('health');
});
