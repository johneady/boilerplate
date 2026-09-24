<?php

namespace App\Notifications\Payments;

use Illuminate\Notifications\Messages\MailMessage;

/**
 * Warns operators that payment gateway credentials were changed.
 *
 * Changing the keys is how payouts would be redirected, so every change is
 * announced to the operations address whoever made it. Like PasswordChanged,
 * it carries no link: a security alert that invites a click trains the reflex
 * phishing depends on.
 */
class PaymentCredentialsChanged extends PaymentNotification
{
    /**
     * @param  list<string>  $changedFields
     */
    public function __construct(
        public readonly string $gateway,
        public readonly array $changedFields,
        public readonly string $changedBy,
        public readonly ?string $ipAddress,
    ) {
        parent::__construct();
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = $this->mailMessage(__('Payment credentials changed'))
            ->greeting(__('Payment credentials changed'))
            ->line(__(':user changed the :gateway credentials.', ['user' => $this->changedBy, 'gateway' => $this->gateway]));

        foreach ($this->changedFields as $field) {
            $message->line('- '.$field);
        }

        if ($this->ipAddress !== null) {
            $message->line(__('Request address: :ip', ['ip' => $this->ipAddress]));
        }

        return $message->line(__('If this was not expected, sign in to the admin panel yourself -- do not follow a link in this email -- and check the payment settings and the audit log.'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return ['gateway' => $this->gateway, 'fields' => $this->changedFields];
    }
}
