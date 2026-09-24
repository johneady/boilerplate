<?php

namespace App\Payments\Drivers\PayPal;

use App\Payments\Exceptions\GatewayException;
use App\Payments\Exceptions\GatewayUnavailable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * A thin client for PayPal's REST API, over Laravel's HTTP client.
 *
 * Laravel's client rather than PayPal's SDK: the older checkout SDK is
 * archived, and Http::fake() makes every PayPal call testable without the
 * network. OAuth tokens are cached per set of credentials until shortly before
 * they lapse, so a checkout costs one API call rather than two.
 */
class PayPalClient
{
    public function __construct(
        public readonly string $baseUrl,
        private readonly string $clientId,
        private readonly string $clientSecret,
        public readonly string $webhookId,
    ) {}

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function get(string $path, array $query = []): array
    {
        return $this->send(fn (PendingRequest $request): Response => $request->get($this->baseUrl.$path, $query));
    }

    /**
     * POST a JSON body. $requestId is sent as PayPal-Request-Id, PayPal's
     * idempotency key: a repeated request with the same id returns the
     * original result rather than doing the work again.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function post(string $path, array $body = [], ?string $requestId = null): array
    {
        return $this->send(function (PendingRequest $request) use ($path, $body, $requestId): Response {
            if ($requestId !== null) {
                $request = $request->withHeaders(['PayPal-Request-Id' => $requestId]);
            }

            // An empty body must go as "{}", not "[]": several PayPal endpoints
            // (order capture, authorization void) reject a JSON array.
            return $request
                ->withBody($body === [] ? '{}' : (string) json_encode($body), 'application/json')
                ->post($this->baseUrl.$path);
        });
    }

    /**
     * @param  \Closure(PendingRequest): Response  $send
     * @return array<string, mixed>
     */
    private function send(\Closure $send): array
    {
        if ($this->clientId === '' || $this->clientSecret === '') {
            throw new GatewayException('No PayPal credentials are configured for this mode.');
        }

        $response = $this->attempt($send, $this->token());

        // A cached token revoked early (credentials rotated in the PayPal
        // dashboard) is refreshed once rather than failing until it lapses.
        if ($response->status() === 401) {
            Cache::forget($this->tokenCacheKey());
            $response = $this->attempt($send, $this->token());
        }

        return $this->decode($response);
    }

    /**
     * @param  \Closure(PendingRequest): Response  $send
     */
    private function attempt(\Closure $send, string $token): Response
    {
        try {
            return $send($this->request()->withToken($token)->acceptJson()->withHeaders([
                'Prefer' => 'return=representation',
            ]));
        } catch (ConnectionException $e) {
            throw new GatewayUnavailable('PayPal could not be reached: '.$e->getMessage(), 0, $e);
        }
    }

    private function token(): string
    {
        /** @var string|null $cached */
        $cached = Cache::get($this->tokenCacheKey());

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        try {
            $response = $this->request()
                ->withBasicAuth($this->clientId, $this->clientSecret)
                ->asForm()
                ->post($this->baseUrl.'/v1/oauth2/token', ['grant_type' => 'client_credentials']);
        } catch (ConnectionException $e) {
            throw new GatewayUnavailable('PayPal could not be reached: '.$e->getMessage(), 0, $e);
        }

        $body = $this->decode($response);
        $token = (string) ($body['access_token'] ?? '');

        if ($token === '') {
            throw new GatewayException('PayPal did not return an access token.');
        }

        $ttl = max(60, (int) ($body['expires_in'] ?? 0) - (int) config('payments.paypal.token_expiry_margin'));
        Cache::put($this->tokenCacheKey(), $token, $ttl);

        return $token;
    }

    /**
     * Keyed by a hash of the credentials, never the credentials themselves,
     * so the cache table holds nothing that could be used to call PayPal.
     */
    private function tokenCacheKey(): string
    {
        return 'payments:paypal-token:'.hash('sha256', $this->baseUrl.'|'.$this->clientId.'|'.$this->clientSecret);
    }

    private function request(): PendingRequest
    {
        return Http::connectTimeout((int) config('payments.http.connect_timeout'))
            ->timeout((int) config('payments.http.timeout'));
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Response $response): array
    {
        if ($response->status() === 429 || $response->serverError()) {
            throw new GatewayUnavailable("PayPal failed to process the request (HTTP {$response->status()}).");
        }

        /** @var array<string, mixed> $body */
        $body = $response->json() ?? [];

        if ($response->failed()) {
            throw new GatewayException($this->errorMessage($body, $response->status()));
        }

        return $body;
    }

    /**
     * PayPal's error shape: a name, a message, and details naming the issue.
     *
     * @param  array<string, mixed>  $body
     */
    private function errorMessage(array $body, int $status): string
    {
        $issue = $body['details'][0]['issue'] ?? $body['name'] ?? $body['error'] ?? null;
        $description = $body['details'][0]['description'] ?? $body['message'] ?? $body['error_description'] ?? null;

        return trim(sprintf('PayPal refused the request (HTTP %d%s)%s', $status, $issue ? ", {$issue}" : '', $description ? ": {$description}" : '.'));
    }
}
