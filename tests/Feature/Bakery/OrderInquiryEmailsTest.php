<?php

use App\Models\OrderInquiry;
use App\Notifications\OrderInquiryAcknowledged;
use App\Notifications\OrderInquiryReceived;
use Illuminate\Notifications\AnonymousNotifiable;

/**
 * The customer's own words reach an email the business trusts, so Markdown in
 * them must render as text rather than a link -- see BaseNotification.
 */
test('the baker alert escapes markdown and html in what the customer typed', function () {
    $inquiry = OrderInquiry::factory()->create([
        'name' => '[invoice](https://evil.example)',
        'details' => "Hello <script>alert(1)</script>\n\n[click](https://evil.example)",
    ]);

    $html = (string) (new OrderInquiryReceived($inquiry))->toMail(new AnonymousNotifiable)->render();

    expect($html)
        ->not->toContain('href="https://evil.example"')
        ->not->toContain('<script>alert(1)</script>')
        ->toContain('&lt;script&gt;');
});

test('the baker alert replies to the customer and links to the order', function () {
    $inquiry = OrderInquiry::factory()->create(['email' => 'sam@example.test']);

    $message = (new OrderInquiryReceived($inquiry))->toMail(new AnonymousNotifiable);

    expect($message->replyTo[0][0])->toBe('sam@example.test')
        ->and($message->actionUrl)->toContain('/admin/order-inquiries/'.$inquiry->id);
});

test('the acknowledgement tells the customer their reference and what happens next', function () {
    $inquiry = OrderInquiry::factory()->create();

    $html = (string) (new OrderInquiryAcknowledged($inquiry))->toMail(new AnonymousNotifiable)->render();

    expect($html)
        ->toContain($inquiry->reference)
        ->toContain('Nothing is baked until you confirm.');
});
