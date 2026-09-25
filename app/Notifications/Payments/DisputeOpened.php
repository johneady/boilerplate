<?php

namespace App\Notifications\Payments;

use App\Models\Dispute;
use App\Settings\Settings;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Tells operators a customer has disputed a payment, once, when it is first
 * seen -- with the deadline for evidence and where to submit it.
 */
class DisputeOpened extends PaymentNotification
{
    public function __construct(public readonly Dispute $dispute)
    {
        parent::__construct();
    }

    public function toMail(object $notifiable): MailMessage
    {
        $dispute = $this->dispute;
        $payment = $dispute->payment;

        $message = $this->mailMessage(__('A payment has been disputed'))
            ->greeting(__('A payment has been disputed'))
            ->line(__(':customer has disputed :amount paid for :description through :gateway (reason: :reason).', [
                'customer' => $payment->customer_name ?? __('A customer'),
                'amount' => $dispute->money()->format(),
                'description' => $payment->description ?? __('a payment'),
                'gateway' => $dispute->gateway->label(),
                'reason' => $dispute->reason ?? __('not given'),
            ]));

        if ($dispute->evidence_due_by !== null) {
            $message->line(__('Evidence must be submitted by :date, or the dispute is lost by default.', [
                'date' => app(Settings::class)->formatDateTime($dispute->evidence_due_by),
            ]));
        }

        return $message->action(__('Respond at :gateway', ['gateway' => $dispute->gateway->label()]), $dispute->dashboardUrl());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return ['dispute' => $this->dispute->gateway_dispute_id];
    }
}
