<?php

namespace App\Bakery;

use App\Models\OrderInquiry;
use App\Notifications\OrderInquiryAcknowledged;
use App\Notifications\OrderInquiryReceived;
use App\Settings\SettingKey;
use App\Settings\Settings;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Sends the two emails around a new order inquiry: the alert to the baker and
 * the acknowledgement to the customer.
 *
 * Each send is wrapped so a mail failure is logged rather than thrown. The
 * inquiry is already stored when this runs, so an unreachable SMTP server must
 * not turn a customer's completed order form into an error page -- they would
 * send it again and the baker would hold two copies.
 */
class InquiryMailer
{
    public function __construct(private readonly Settings $settings) {}

    /**
     * Alert the baker and acknowledge the customer.
     *
     * A blank BusinessEmail skips the alert, as for the contact form: that
     * setting's help text says blank hides the address, so it must not mean
     * "mail it anyway". The customer's acknowledgement goes regardless.
     */
    public function received(OrderInquiry $inquiry): void
    {
        $bakerAddress = $this->settings->string(SettingKey::BusinessEmail);

        if ($bakerAddress !== '') {
            $this->send($bakerAddress, new OrderInquiryReceived($inquiry), $inquiry);
        }

        $this->send($inquiry->email, new OrderInquiryAcknowledged($inquiry), $inquiry);
    }

    /**
     * Send one notification to a bare address, logging any failure.
     */
    private function send(string $address, BaseNotification $notification, OrderInquiry $inquiry): void
    {
        try {
            Notification::route('mail', $address)->notify($notification);
        } catch (Throwable $exception) {
            Log::error('Failed to send an order inquiry email.', [
                'inquiry_id' => $inquiry->id,
                'notification' => $notification::class,
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
