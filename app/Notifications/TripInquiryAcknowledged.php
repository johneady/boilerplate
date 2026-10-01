<?php

namespace App\Notifications;

use App\Models\TripInquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Tells the traveller their request arrived and what happens next.
 *
 * Deliberately says "request", never "booking": nothing is held until the
 * agency confirms availability and the deposit is paid.
 */
class TripInquiryAcknowledged extends BaseNotification implements ShouldQueue
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

        $message = $this->mailMessage(__('We have your trip request (:reference)', ['reference' => $inquiry->reference]))
            ->greeting(__('Thanks, :name!', ['name' => $this->escapeMarkdownTokens($inquiry->name)]))
            ->line(__('Your request for :trip has reached our travel team. Your reference is :reference.', [
                'trip' => $this->escapeMarkdownTokens($inquiry->tripLabel()),
                'reference' => $inquiry->reference,
            ]));

        if ($inquiry->departure !== null) {
            $message->line(__('Departure: :dates', ['dates' => $inquiry->departure->dateRange()]));
        }

        if ($inquiry->formattedQuote() !== null) {
            $message->line(__('Estimated total: :total for :count travellers.', [
                'total' => $inquiry->formattedQuote(),
                'count' => $inquiry->travellers(),
            ]));
        }

        return $message
            ->line(__('A trip specialist will confirm availability and reply within one business day. Nothing is charged until you approve the final quote.'))
            ->line(__('Questions in the meantime? Just reply to this email.'));
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
