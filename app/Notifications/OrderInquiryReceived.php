<?php

namespace App\Notifications;

use App\Filament\Resources\OrderInquiries\OrderInquiryResource;
use App\Models\OrderInquiry;
use App\Settings\Settings;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\HtmlString;

/**
 * Tells the baker a new order inquiry has arrived.
 *
 * Unqueued, like ContactSubmissionReceived and for the same reason: the inquiry
 * is stored before this is sent and the caller logs and swallows a failure, so
 * a queue would only add a second way for the alert to go missing.
 *
 * The reply-to is the customer, so answering the email answers them. Every
 * customer-typed value is Markdown-escaped -- see BaseNotification.
 */
class OrderInquiryReceived extends BaseNotification
{
    public function __construct(private readonly OrderInquiry $inquiry) {}

    /**
     * Build the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $inquiry = $this->inquiry;

        $message = $this->mailMessage(__('New order inquiry :reference', ['reference' => $inquiry->reference]))
            ->greeting(__('New order inquiry :reference', ['reference' => $inquiry->reference]))
            ->replyTo($inquiry->email, $inquiry->name)
            ->line(__('From: :name (:email)', [
                'name' => $this->escapeMarkdownTokens($inquiry->name),
                'email' => $this->escapeMarkdownTokens($inquiry->email),
            ]));

        if (filled($inquiry->phone)) {
            $message->line(__('Phone: :phone', ['phone' => $this->escapeMarkdownTokens($inquiry->phone)]));
        }

        $message
            ->line(__('Needed on: :date (:fulfilment)', [
                'date' => app(Settings::class)->formatDate($inquiry->needed_on),
                'fulfilment' => __($inquiry->fulfilment->label()),
            ]));

        if (filled($inquiry->delivery_address)) {
            $message->line(__('Deliver to: :address', ['address' => $this->escapeMarkdownTokens($inquiry->delivery_address)]));
        }

        if ($inquiry->occasion !== null) {
            $message->line(__('Occasion: :occasion', ['occasion' => __($inquiry->occasion->label())]));
        }

        $message->line(__('They would like:'));

        foreach ($inquiry->items as $line) {
            $message->line(__(':quantity × :item (:unit)', [
                'quantity' => $line['quantity'],
                'item' => $this->escapeMarkdownTokens($line['name']),
                'unit' => $this->escapeMarkdownTokens($line['price_unit']),
            ]));
        }

        $message->line(__('Menu estimate: :total', ['total' => $inquiry->formattedEstimate()]));

        if (filled($inquiry->details)) {
            $message->line(__('Their notes:'));
            $message->line(new HtmlString($this->quotedText($inquiry->details)));
        }

        if (filled($inquiry->allergies)) {
            $message->line(__('Allergies and dietary needs:'));
            $message->line(new HtmlString($this->quotedText($inquiry->allergies)));
        }

        return $message
            ->action(__('Open in the admin panel'), OrderInquiryResource::getUrl('view', ['record' => $inquiry], panel: 'admin'))
            ->line(__('Reply to this email to answer the customer directly.'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array{reference: string, needed_on: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'reference' => $this->inquiry->reference,
            'needed_on' => $this->inquiry->needed_on->toDateString(),
        ];
    }
}
