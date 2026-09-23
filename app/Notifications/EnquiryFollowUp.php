<?php

namespace App\Notifications;

use App\Models\Enquiry;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\URL;
use InvalidArgumentException;

/**
 * One email of the automatic sequence a customer receives after enquiring:
 * immediately, then on days 2, 5, 10 and 20 (config('voltiva.follow_up_days')).
 *
 * Each step has one job -- confirm, invite to a test drive, explain charging
 * and range, explain registration and finance, then a last check-in -- and
 * every one carries a link to stop the emails. The wording is placeholder
 * copy for Voltiva to replace or approve; it lives in the translation files,
 * so each customer gets it in the language they enquired in (the enquiry's
 * locale is applied when it is sent).
 */
class EnquiryFollowUp extends BaseNotification
{
    public function __construct(
        private readonly Enquiry $enquiry,
        public readonly int $step,
    ) {
        if ($step < 1 || $step > Enquiry::followUpCount()) {
            throw new InvalidArgumentException("There is no follow-up email number {$step}.");
        }
    }

    /**
     * Build the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $business = $this->businessName();
        $car = $this->enquiry->vehicle_name ?? __('your new electric car');

        $message = (new MailMessage)
            ->greeting(__('Hello :name,', ['name' => $this->escapeMarkdownTokens($this->enquiry->name)]));

        $message = match ($this->step) {
            1 => $message
                ->subject(__('Thank you for your enquiry – :business', ['business' => $business]))
                ->line(__('Thank you for your interest in :car. We have received your enquiry and a member of our team in Mallorca will contact you within one working day.', ['car' => $car]))
                ->line(__('In the meantime, you can compare our cars side by side or read our guides to batteries, charging and registration.'))
                ->action(__('Compare our cars'), route('compare')),
            2 => $message
                ->subject(__('Would you like a test drive? – :business', ['business' => $business]))
                ->line(__('The best way to decide is to drive one. We can bring :car to you anywhere in Mallorca, or you can visit us in Palma.', ['car' => $car]))
                ->line(__('Just reply to this email with a day and time that suits you.'))
                ->action(__('View the car range'), route('cars.index')),
            3 => $message
                ->subject(__('How far will it go? Range and charging explained – :business', ['business' => $business]))
                ->line(__('Most of our customers drive less than 40 km a day, and charge overnight from a normal household socket – no wallbox needed.'))
                ->line(__('Our short guide explains real-world range, how long a battery lasts and what a full charge costs.'))
                ->action(__('Read about batteries and range'), route('pages.show', 'batteries-and-range')),
            4 => $message
                ->subject(__('Registration and finance, handled for you – :business', ['business' => $business]))
                ->line(__('We take care of the paperwork: the Certificate of Conformity, registration with the DGT and your number plates. Your car arrives ready to drive.'))
                ->line(__('If you would like to spread the cost, we can also prepare a finance quote with no obligation.'))
                ->action(__('See finance options'), route('pages.show', 'finance')),
            default => $message
                ->subject(__('Are you still looking for an electric car? – :business', ['business' => $business]))
                ->line(__('We wanted to check in one last time. If you have any questions about :car, or would like a quote, simply reply to this email.', ['car' => $car]))
                ->line(__('This is the last email in this series – we will not contact you again unless you ask us to.'))
                ->action(__('Talk to us'), route('enquiry', ['vehicle' => $this->enquiry->vehicle?->slug])),
        };

        return $message
            ->salutation(__('The :business team', ['business' => $business]))
            ->line(__('Prefer not to receive these emails? [Stop the follow-up emails](:url).', [
                'url' => $this->stopUrl(),
            ]));
    }

    /**
     * A signed link that ends the sequence for this enquiry.
     */
    private function stopUrl(): string
    {
        if (! $this->enquiry->exists) {
            return url('/');
        }

        return URL::signedRoute('enquiry.stop-emails', ['enquiry' => $this->enquiry]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array{enquiry_id: int, step: int}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'enquiry_id' => $this->enquiry->id,
            'step' => $this->step,
        ];
    }
}
