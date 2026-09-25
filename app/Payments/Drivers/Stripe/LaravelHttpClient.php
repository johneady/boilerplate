<?php

namespace App\Payments\Drivers\Stripe;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Stripe\Exception\ApiConnectionException;
use Stripe\Exception\UnexpectedValueException;
use Stripe\HttpClient\ClientInterface;
use Stripe\Util\CaseInsensitiveArray;
use Stripe\Util\Util;

/**
 * Sends stripe-php's requests through Laravel's HTTP client.
 *
 * The SDK's own CurlClient is invisible to Http::fake(), which would leave the
 * Stripe driver the one integration that could not be tested without the
 * network. Routing it through Http means the same fakes, the same
 * preventStrayRequests() safety net and the same timeouts cover both
 * gateways. Encoding follows CurlClient exactly (Util::encodeParameters), so
 * Stripe receives the same bytes either way.
 *
 * Installed process-wide by StripeDriver: stripe-php only supports a global
 * client (ApiRequestor::setHttpClient).
 */
class LaravelHttpClient implements ClientInterface
{
    /**
     * @param  'delete'|'get'|'post'  $method
     * @param  string  $absUrl
     * @param  array<int, string>  $headers
     * @param  array<string, mixed>  $params
     * @param  bool  $hasFile
     * @param  'v1'|'v2'  $apiMode
     * @param  int|null  $maxNetworkRetries
     * @return array{0: string, 1: int, 2: CaseInsensitiveArray}
     */
    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
    {
        if ($hasFile) {
            throw new UnexpectedValueException('File uploads are not used by this application.');
        }

        $params = Util::objectsToIds($params, false);
        $body = '';

        if ($method === 'post') {
            $body = $apiMode === 'v2' ? (string) json_encode($params) : Util::encodeParameters($params);
        } elseif ($params !== []) {
            $absUrl .= '?'.Util::encodeParameters($params, $apiMode);
        }

        $request = Http::withHeaders($this->parseHeaders($headers))
            ->connectTimeout((int) config('payments.http.connect_timeout'))
            ->timeout((int) config('payments.http.timeout'));

        if ($method === 'post') {
            $request = $request->withBody($body, $apiMode === 'v2' ? 'application/json' : 'application/x-www-form-urlencoded');
        }

        try {
            $response = $request->send(strtoupper((string) $method), $absUrl);
        } catch (ConnectionException $e) {
            throw new ApiConnectionException('Could not connect to Stripe: '.$e->getMessage(), 0, $e);
        }

        $responseHeaders = new CaseInsensitiveArray;

        foreach ($response->headers() as $name => $values) {
            $responseHeaders[$name] = implode(', ', $values);
        }

        return [$response->body(), $response->status(), $responseHeaders];
    }

    /**
     * stripe-php passes headers as "Name: value" strings.
     *
     * @param  array<int, string>  $headers
     * @return array<string, string>
     */
    private function parseHeaders(array $headers): array
    {
        $parsed = [];

        foreach ($headers as $header) {
            [$name, $value] = array_pad(explode(':', $header, 2), 2, '');
            $parsed[trim($name)] = trim($value);
        }

        return $parsed;
    }
}
