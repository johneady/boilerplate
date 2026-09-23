<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\URL;

/**
 * Confirms a purchase to the customer and links to their order.
 *
 * Unqueued, like ContactSubmissionReceived and for the same reason: the order
 * is already stored before this is sent, and the caller logs and swallows a
 * send failure, so the queue would only add a second way for the mail to go
 * missing. Sent on demand to the address given at checkout, since most
 * customers buy without an account.
 */
class OrderConfirmed extends BaseNotification
{
    public function __construct(private readonly Order $order) {}

    /**
     * Build the mail representation of the notification.
     *
     * The customer's name is Markdown-escaped: the mail template parses every
     * line as Markdown, and the name is whatever was typed at checkout.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $message = $this->mailMessage(__('Your order :reference', ['reference' => $this->order->reference]))
            ->greeting(__('Thank you, :name!', ['name' => $this->escapeMarkdownTokens($this->order->customer_name)]))
            ->line(__('Your order :reference is confirmed. Here is what you bought:', ['reference' => $this->order->reference]));

        $this->order->items->each(function (OrderItem $item) use ($message): void {
            $message->line(__(':package — :price', [
                'package' => $this->escapeMarkdownTokens($item->package_title),
                'price' => $item->formattedPrice(),
            ]));
        });

        return $message
            ->line(__('Total paid: :total', ['total' => $this->order->formattedTotal()]))
            ->action(__('View your order'), URL::signedRoute('orders.show', $this->order))
            ->line(__('Your licence covers royalty-free commercial use worldwide. Keep this email as your proof of purchase.'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array{reference: string, total_cents: int}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'reference' => $this->order->reference,
            'total_cents' => $this->order->total_cents,
        ];
    }
}
