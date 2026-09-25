<?php

namespace App\Payments\Data;

/**
 * A webhook delivery whose signature has been checked.
 */
final readonly class VerifiedWebhook
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $eventId,
        public string $type,
        public array $payload,
    ) {}
}
