<?php

namespace App\Notifications;

use App\Filament\Resources\TripInquiries\TripInquiryResource;
use App\Models\TripInquiry;
use App\Travel\Price;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Tells the agency a new booking request has arrived.
 *
 * Queued for the same reason ContactSubmissionReceived is: the request is
 * stored before this is dispatched, so a send that fails in the queue loses
 * nothing. The reply-to is the traveller, so answering the email answers them.
 */
class TripInquiryReceived extends BaseNotification implements ShouldQueue
{
    use Queueable;

    public bool $deleteWhenMissingModels = true;

    public function __construct(private readonly TripInquiry $inquiry) {}

    /**
     * Build the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $inquiry = $this->inquiry;

        $message = $this->mailMessage(__('New booking request :reference', ['reference' => $inquiry->reference]))
            ->greeting(__('New booking request :reference', ['reference' => $inquiry->reference]))
            ->replyTo($inquiry->email, $inquiry->name)
            ->line(__('From: :name (:email)', [
                'name' => $this->escapeMarkdownTokens($inquiry->name),
                'email' => $this->escapeMarkdownTokens($inquiry->email),
            ]));

        if (filled($inquiry->phone)) {
            $message->line(__('Phone: :phone', ['phone' => $this->escapeMarkdownTokens($inquiry->phone)]));
        }

        $message->line(__('Trip: :trip', ['trip' => $this->escapeMarkdownTokens($inquiry->tripLabel())]));

        if ($inquiry->departure !== null) {
            $message->line(__('Departure: :dates', ['dates' => $inquiry->departure->dateRange()]));
        } elseif (filled($inquiry->travel_month)) {
            $message->line(__('Travelling: :month', ['month' => $this->escapeMarkdownTokens($inquiry->travel_month)]));
        }

        $message->line(__('Party: :adults adults, :children children', [
            'adults' => $inquiry->adults,
            'children' => $inquiry->children,
        ]));

        if ($inquiry->quoted_total_cents !== null) {
            $message->line(__('Quoted on the site: :total', ['total' => Price::format($inquiry->quoted_total_cents)]));
        }

        if (filled($inquiry->message)) {
            $message->line(__('Their notes: :message', ['message' => $this->escapeMarkdownTokens($inquiry->message)]));
        }

        return $message
            ->action(__('Open in the admin panel'), TripInquiryResource::getUrl('view', ['record' => $inquiry], panel: 'admin'))
            ->line(__('Reply to this email to answer the traveller directly.'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array{reference: string}
     */
    public function toArray(object $notifiable): array
    {
        return ['reference' => $this->inquiry->reference];
    }
}
