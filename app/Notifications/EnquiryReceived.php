<?php

namespace App\Notifications;

use App\Filament\Resources\Enquiries\EnquiryResource;
use App\Models\Enquiry;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Tells the sales team a new enquiry has arrived.
 *
 * Sent to the BusinessEmail setting on demand, like the contact form alert,
 * and carries the fields the team triages on -- car, location, finance and
 * registration interest -- so the first call can be made from the inbox. The
 * full record is in the admin panel, linked below.
 */
class EnquiryReceived extends BaseNotification
{
    public function __construct(private readonly Enquiry $enquiry) {}

    /**
     * Build the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $enquiry = $this->enquiry;
        $yes = __('Yes');
        $no = __('No');

        // The subject is plain text, never parsed as Markdown, so the name goes
        // in unescaped -- escaping it would print "Jean\-Luc" in the inbox.
        $message = $this->mailMessage(__('New enquiry from :name', ['name' => $enquiry->name]))
            ->greeting(__('New customer enquiry'))
            ->replyTo($enquiry->email, $enquiry->name)
            ->line(__('Customer: :name (:email)', [
                'name' => $this->escapeMarkdownTokens($enquiry->name),
                'email' => $this->escapeMarkdownTokens($enquiry->email),
            ]));

        if (filled($enquiry->phone)) {
            $message->line(__('Phone: :phone', ['phone' => $this->escapeMarkdownTokens((string) $enquiry->phone)]));
        }

        $message->line(__('Car: :vehicle', ['vehicle' => $enquiry->vehicle_name ?? __('Not sure yet')]))
            ->line(__('Location: :location', ['location' => $this->escapeMarkdownTokens((string) ($enquiry->location ?: '—'))]))
            ->line(__('Interested in finance: :answer', ['answer' => $enquiry->finance_interest ? $yes : $no]))
            ->line(__('Wants registration handled: :answer', ['answer' => $enquiry->registration_interest ? $yes : $no]))
            ->line(__('Came from: :source', ['source' => __($enquiry->sourceLabel())]));

        if (filled($enquiry->message)) {
            $message->line(__('Message: :message', ['message' => $this->escapeMarkdownTokens((string) $enquiry->message)]));
        }

        return $message
            ->action(__('Open the enquiry'), $enquiry->exists ? EnquiryResource::getUrl('view', ['record' => $enquiry]) : url('/admin'))
            ->line(__('The customer has been sent the first email of the follow-up sequence.'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array{id: int, name: string, vehicle: string|null}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'id' => $this->enquiry->id,
            'name' => $this->enquiry->name,
            'vehicle' => $this->enquiry->vehicle_name,
        ];
    }
}
