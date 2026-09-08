<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | The proxies whose "X-Forwarded-*" headers Laravel will trust. This is the
    | key Illuminate\Http\Middleware\TrustProxies reads at request time; that
    | middleware is already in the default global stack, so no registration in
    | bootstrap/app.php is needed — a null value here is simply what leaves it
    | trusting nothing.
    |
    | This matters behind a TLS-terminating reverse proxy. Traefik on the
    | Dokploy deploy terminates TLS and forwards PLAIN HTTP to the container
    | with an X-Forwarded-Proto: https header. Untrusted, that header is
    | ignored, Laravel reads the request as insecure, and every url(), asset()
    | and @vite URL is generated as http:// on an https:// page — which the
    | browser blocks as mixed content, taking the stylesheet, the JS bundle and
    | Flux's script down with it.
    |
    | docker-compose.dokploy.yml sets TRUST_PROXIES=*. "*" trusts whichever
    | proxy forwarded the request, which is safe there precisely because
    | nothing is published: only Traefik can reach the container, so no
    | arbitrary client can spoof its own scheme or IP.
    |
    | Leave TRUST_PROXIES unset on a same-host nginx -> php-fpm deploy. There
    | the request already arrives secure, and trusting these headers from any
    | client would let it spoof both. A comma-separated list of addresses names
    | specific proxies instead.
    |
    */

    'proxies' => match ($proxies = env('TRUST_PROXIES')) {
        null, '' => null,
        '*' => '*',
        default => array_values(array_filter(array_map('trim', explode(',', (string) $proxies)))),
    },

];
