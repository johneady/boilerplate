<?php

namespace App\Payments\Enums;

/**
 * What became of a received webhook event.
 */
enum WebhookEventStatus: string
{
    /** Stored and queued; not yet processed. */
    case Received = 'received';

    case Processed = 'processed';

    /** A type this application does not act on, or for a payment it does not know. */
    case Ignored = 'ignored';

    /** Processing exhausted its retries. An administrator can retry it. */
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Received => 'Received',
            self::Processed => 'Processed',
            self::Ignored => 'Ignored',
            self::Failed => 'Failed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Received => 'gray',
            self::Processed => 'success',
            self::Ignored => 'zinc',
            self::Failed => 'danger',
        };
    }
}
