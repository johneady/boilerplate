<?php

namespace App\Payments;

use App\Settings\SettingKey;
use App\Settings\Settings;
use Illuminate\Notifications\Notification as NotificationContract;
use Illuminate\Support\Facades\Notification;

/**
 * Send a payments alert to the operations address, if one is set.
 *
 * The same address the queue-failure alert uses (Settings -> Email). A blank
 * address sends nothing -- that setting's help text says blank means "send no
 * alerts" -- and the condition is still visible in the admin panel.
 */
class OpsAlerts
{
    public function __construct(private readonly Settings $settings) {}

    public function send(NotificationContract $notification): void
    {
        $recipient = $this->settings->string(SettingKey::OpsAlertEmail);

        if ($recipient === '') {
            return;
        }

        Notification::route('mail', $recipient)->notify($notification);
    }
}
