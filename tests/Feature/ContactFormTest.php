<?php

use App\Livewire\Contact;
use App\Models\ContactSubmission;
use App\Models\Page;
use App\Notifications\ContactSubmissionReceived;
use App\Settings\SettingKey;
use App\Settings\Settings;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function (): void {
    Notification::fake();

    app(Settings::class)->set(SettingKey::BusinessEmail, 'hello@example.com');

    // Cleared between tests: both limiter keys are keyed on the request IP,
    // which is the same 127.0.0.1 for every test in the file, so a test that
    // trips a limit would otherwise fail the next one.
    RateLimiter::clear('contact-form:127.0.0.1');
    RateLimiter::clear('contact-form-rejections:127.0.0.1');
});

/**
 * The minimum fill time is measured from mount(), so a test submitting
 * immediately looks automated. Every test that expects a stored row travels
 * past it rather than lowering the threshold.
 */
function submitContactForm(array $overrides = []): Testable
{
    $component = Livewire::test(Contact::class)
        ->set('name', 'Sam Visitor')
        ->set('email', 'sam@example.test')
        ->set('subject', 'A question')
        ->set('message', 'Do your widgets come in blue? I need about forty of them.');

    foreach ($overrides as $property => $value) {
        $component->set($property, $value);
    }

    test()->travel(5)->seconds();

    return $component->call('submit');
}

test('the contact page renders for a guest', function () {
    $this->get('/contact')
        ->assertSuccessful()
        ->assertSee('Send message')
        ->assertSee('Your email address');
});

test('the contact page shows the copy from its page row', function () {
    Page::factory()->published()->create([
        'slug' => 'contact',
        'title' => 'Talk to us',
        'body' => 'We answer within two working days.',
    ]);

    $this->get('/contact')
        ->assertSuccessful()
        ->assertSee('Talk to us')
        ->assertSee('We answer within two working days.')
        ->assertSee('<title>Talk to us - '.e(config('app.name')).'</title>', false);
});

test('the contact page falls back to its own copy with no page row', function () {
    $this->get('/contact')
        ->assertSuccessful()
        ->assertSee('Contact')
        ->assertSee('Send us a message and we will get back to you.');
});

/**
 * An unpublished contact page row must not supply the public copy.
 */
test('an unpublished contact page row is ignored', function () {
    Page::factory()->create(['slug' => 'contact', 'title' => 'Draft Contact Title']);

    $this->get('/contact')
        ->assertSuccessful()
        ->assertDontSee('Draft Contact Title');
});

test('a submission is stored', function () {
    submitContactForm()->assertHasNoErrors();

    $submission = ContactSubmission::sole();

    expect($submission->name)->toBe('Sam Visitor')
        ->and($submission->email)->toBe('sam@example.test')
        ->and($submission->subject)->toBe('A question')
        ->and($submission->message)->toContain('widgets come in blue')
        ->and($submission->handled_at)->toBeNull()
        ->and($submission->ip_address)->not->toBeNull();
});

test('a submission notifies the business address', function () {
    submitContactForm()->assertHasNoErrors();

    Notification::assertSentOnDemand(
        ContactSubmissionReceived::class,
        fn (ContactSubmissionReceived $notification, array $channels, object $notifiable): bool => $notifiable->routes['mail'] === 'hello@example.com',
    );
});

test('the form is cleared and reports success', function () {
    submitContactForm()
        ->assertSet('name', '')
        ->assertSet('email', '')
        ->assertSet('message', '')
        ->assertSet('sent', true);
});

test('a blank subject is stored as null rather than an empty string', function () {
    submitContactForm(['subject' => ''])->assertHasNoErrors();

    expect(ContactSubmission::sole()->subject)->toBeNull();
});

/**
 * The setting's own help text says a blank address hides it, so blank must not
 * mean "mail it anyway". The submission is still stored -- that is the point of
 * storing first.
 */
test('a blank business email stores the submission but sends nothing', function () {
    app(Settings::class)->set(SettingKey::BusinessEmail, '');

    submitContactForm()->assertHasNoErrors();

    expect(ContactSubmission::count())->toBe(1);

    Notification::assertNothingSent();
});

test('validation rejects an empty form', function () {
    Livewire::test(Contact::class)
        ->call('submit')
        ->assertHasErrors(['name' => 'required', 'email' => 'required', 'message' => 'required']);

    expect(ContactSubmission::count())->toBe(0);
});

test('validation rejects a malformed email address', function () {
    Livewire::test(Contact::class)
        ->set('name', 'Sam')
        ->set('email', 'not-an-email')
        ->set('message', 'A message long enough to pass the minimum.')
        ->call('submit')
        ->assertHasErrors(['email' => 'email']);

    expect(ContactSubmission::count())->toBe(0);
});

test('validation rejects a message that is too short', function () {
    Livewire::test(Contact::class)
        ->set('name', 'Sam')
        ->set('email', 'sam@example.test')
        ->set('message', 'Hi')
        ->call('submit')
        ->assertHasErrors(['message' => 'min']);
});

/**
 * The spam checks report success rather than an error: telling a bot which check
 * caught it is how the next version gets past both.
 */
test('a submission with the honeypot filled is silently discarded', function () {
    submitContactForm(['website' => 'http://spam.example'])
        ->assertHasNoErrors()
        ->assertSet('sent', true);

    expect(ContactSubmission::count())->toBe(0);

    Notification::assertNothingSent();
});

test('a submission faster than a person can type is silently discarded', function () {
    // No travel(): submitted in the same second the form was mounted.
    Livewire::test(Contact::class)
        ->set('name', 'Sam Visitor')
        ->set('email', 'sam@example.test')
        ->set('message', 'Do your widgets come in blue? I need about forty.')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('sent', true);

    expect(ContactSubmission::count())->toBe(0);

    Notification::assertNothingSent();
});

test('the rate limiter turns away a sixth submission from one address', function () {
    foreach (range(1, 5) as $attempt) {
        submitContactForm(['email' => "sam{$attempt}@example.test"])->assertHasNoErrors();
    }

    expect(ContactSubmission::count())->toBe(5);

    submitContactForm(['email' => 'sam6@example.test'])
        ->assertHasErrors('message');

    expect(ContactSubmission::count())->toBe(5);
});

/**
 * A discarded spam submission must not consume the sender's allowance, or a
 * bot hitting the honeypot would exhaust a shared address's quota for an hour
 * and lock out everybody behind that NAT.
 */
test('a discarded spam submission does not count against the rate limit', function () {
    foreach (range(1, 8) as $attempt) {
        submitContactForm(['website' => 'http://spam.example'])->assertHasNoErrors();
    }

    submitContactForm()->assertHasNoErrors();

    expect(ContactSubmission::count())->toBe(1);
});

/**
 * Rejections count against their own budget, so a bot hammering the form is
 * eventually cut off without the human limiter ever being involved.
 */
test('enough rejected submissions exhaust their own budget and the address is refused', function () {
    foreach (range(1, 30) as $attempt) {
        submitContactForm(['website' => 'http://spam.example'])->assertHasNoErrors();
    }

    // The 31st is refused with the same generic copy as the human limiter,
    // and still stores nothing.
    submitContactForm(['website' => 'http://spam.example'])->assertHasErrors('message');

    expect(ContactSubmission::count())->toBe(0);
});

test('the business notification is queued rather than sent inline', function () {
    // The visitor's request should end at the dispatch: the SMTP round trip
    // belongs to a worker. Pinned on the class rather than by observing the
    // queue because this file fakes the Notification facade to assert sends,
    // and a fake intercepts before anything is dispatched.
    expect(new ContactSubmissionReceived(ContactSubmission::factory()->make()))
        ->toBeInstanceOf(ShouldQueue::class);
});

/**
 * The submission is stored before the notification is attempted, and the send is
 * wrapped, so a broken mailer cannot lose a message somebody already sent.
 */
test('a failing mailer does not lose the submission', function () {
    Notification::shouldReceive('route')->andThrow(new RuntimeException('SMTP is down'));

    submitContactForm()->assertHasNoErrors()->assertSet('sent', true);

    expect(ContactSubmission::count())->toBe(1);
});

test('the notification carries the sender as the reply-to address', function () {
    $submission = ContactSubmission::factory()->make([
        'name' => 'Sam Visitor',
        'email' => 'sam@example.test',
        'subject' => 'A question',
        'message' => "First paragraph.\n\nSecond paragraph.",
    ]);

    $mail = (new ContactSubmissionReceived($submission))->toMail(new stdClass);

    expect($mail->replyTo)->toBe([['sam@example.test', 'Sam Visitor']])
        ->and($mail->subject)->toContain('New contact form message');

    // Quoted as a blockquote with the blank line prefixed too, so both
    // paragraphs stay inside one quote. Held as an HtmlString rather than a
    // plain string, which is what keeps the newlines -- a plain line() collapses
    // them into one unbroken paragraph.
    $quoted = collect($mail->introLines)
        ->map(fn (mixed $line): string => (string) $line)
        ->first(fn (string $line): bool => str_starts_with($line, '>'));

    expect($quoted)->toBe("> First paragraph.\n>\n> Second paragraph.");
});

/**
 * The quoted body is passed through HtmlString to keep its line breaks, which
 * opts it out of the mail template's escaping. A stranger wrote it, so the
 * notification has to escape it itself.
 */
test('html in a submitted message is escaped in the notification', function () {
    $submission = ContactSubmission::factory()->make([
        'message' => 'Hello <script>alert("xss")</script> there',
    ]);

    $mail = (new ContactSubmissionReceived($submission))->toMail(new stdClass);

    $quoted = collect($mail->introLines)
        ->map(fn (mixed $line): string => (string) $line)
        ->first(fn (string $line): bool => str_starts_with($line, '>'));

    expect($quoted)
        ->not->toContain('<script>')
        ->toContain('&lt;script&gt;');
});

test('markdown in a submitted message renders as text, not as a link', function () {
    $submission = ContactSubmission::factory()->make([
        'message' => 'See [our offer](https://evil.example) and *terms*',
    ]);

    $mail = (new ContactSubmissionReceived($submission))->toMail(new stdClass);

    $html = (string) $mail->render();

    // The mail template parses Markdown after this notification's own
    // escaping, so HTML-escaping alone leaves [text](url) free to render as a
    // clickable anchor -- an attacker-controlled link inside a notification
    // the business has every reason to trust.
    expect($html)
        ->not->toContain('href="https://evil.example"')
        ->not->toContain('<em>terms</em>')
        ->toContain('[our offer](https://evil.example)');
});
