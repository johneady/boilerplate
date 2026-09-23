<?php

namespace App\Notifications;

use App\Models\OrderInquiry;
use App\Settings\Settings;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Confirms to the customer that their order inquiry arrived, and sets out what
 * happens next.
 *
 * Sent on demand to the address typed on the form -- customers do not need an
 * account to order. Unqueued, like the baker's alert: the inquiry is already
 * stored and a send failure is logged, not thrown.
 */
class OrderInquiryAcknowledged extends BaseNotification
{
    public function __construct(private readonly OrderInquiry $inquiry) {}

    /**
     * Build the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $inquiry = $this->inquiry;

        $message = $this->mailMessage(__('We received your order request :reference', ['reference' => $inquiry->reference]))
            ->greeting(__('Thank you, :name!', ['name' => $this->escapeMarkdownTokens($inquiry->name)]))
            ->line(__('Your order request :reference has reached our kitchen. Here is what you asked for:', ['reference' => $inquiry->reference]));

        foreach ($inquiry->items as $line) {
            $message->line(__(':quantity × :item (:unit)', [
                'quantity' => $line['quantity'],
                'item' => $this->escapeMarkdownTokens($line['name']),
                'unit' => $this->escapeMarkdownTokens($line['price_unit']),
            ]));
        }

        return $message
            ->line(__(':fulfilment on :date. Menu estimate: :total.', [
                'fulfilment' => __($inquiry->fulfilment->label()),
                'date' => app(Settings::class)->formatDate($inquiry->needed_on),
                'total' => $inquiry->formattedEstimate(),
            ]))
            ->line(__('What happens next: we check the date against our baking calendar and reply within one day with a confirmed price and how to pay. Nothing is baked until you confirm.'))
            ->line(__('Need to change something? Just reply to this email and quote your reference.'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array{reference: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'reference' => $this->inquiry->reference,
        ];
    }
}
