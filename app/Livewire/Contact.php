<?php

namespace App\Livewire;

use App\Concerns\ContactValidationRules;
use App\Models\ContactSubmission;
use App\Models\Page;
use App\Notifications\ContactSubmissionReceived;
use App\Settings\SettingKey;
use App\Settings\Settings;
use Flux\Flux;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Throwable;

/**
 * The public contact form.
 *
 * A Livewire component rather than a controller and a POST route, because every
 * other form in this application is one, and because the inline validation
 * errors are the reason people finish forms.
 *
 * The order of work in submit() is the important part: the submission is written
 * to the database BEFORE the notification is attempted, and the notification is
 * wrapped so a failure cannot take the request down with it. The mailer's
 * unsaved default is 'log', so on a fresh instance nothing is delivered anywhere
 * -- persisting first is what stops that silently discarding a visitor's message.
 */
class Contact extends Component
{
    use ContactValidationRules;

    /**
     * How many submissions one address may send per hour.
     *
     * Generous enough that a person who sends a message, spots a typo and sends
     * a correction is unaffected, low enough that a script gets nowhere. The
     * honeypot below catches the naive bots; this bounds the rest.
     */
    private const int MAX_ATTEMPTS_PER_HOUR = 5;

    /**
     * The shortest time a genuine submission can plausibly take, in seconds.
     */
    private const int MINIMUM_FILL_SECONDS = 3;

    public string $name = '';

    public string $email = '';

    public string $subject = '';

    public string $message = '';

    /**
     * The honeypot field, hidden from people and irresistible to bots.
     *
     * A real visitor never sees it, so anything in it means the form was filled
     * by something automated.
     */
    public string $website = '';

    /**
     * When the form was rendered, as a unix timestamp.
     *
     * Submitted faster than a person can type means the form was not typed into.
     */
    public int $renderedAt = 0;

    /**
     * Whether the form has been submitted successfully.
     */
    public bool $sent = false;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->renderedAt = now()->getTimestamp();
    }

    /**
     * Validate and record a submission.
     *
     * The two spam checks report success without storing anything. Telling a bot
     * which check caught it is how the next version of that bot gets past both,
     * and a person who somehow trips one -- a password manager filling every
     * field it can see -- is better served by a form that appears to work than
     * by an accusation they cannot act on.
     */
    public function submit(): void
    {
        $this->ensureIsNotRateLimited();

        $validated = $this->validate($this->contactRules());

        if ($this->looksAutomated()) {
            $this->finish();

            return;
        }

        RateLimiter::increment($this->rateLimitKey(), 3600);

        $submission = ContactSubmission::create([
            ...$validated,
            'subject' => $validated['subject'] ?: null,
            'ip_address' => request()->ip(),
            // Truncated to the column width rather than left to the database:
            // a header this long is a broken client or a probe, and MySQL in
            // strict mode would reject the insert and lose the message.
            'user_agent' => mb_substr((string) request()->userAgent(), 0, 255) ?: null,
        ]);

        $this->notifyBusiness($submission);

        $this->finish();
    }

    /**
     * The page row supplying this form's heading and intro copy.
     *
     * Null when nobody has created it, in which case the view falls back to its
     * own translated copy -- the form must work on an instance whose pages were
     * never seeded.
     */
    #[Computed]
    public function page(): ?Page
    {
        return Page::query()->published()->where('slug', 'contact')->first();
    }

    /**
     * Refuse a submission from an address that has sent too many already.
     *
     * Keyed on the address rather than the session, because a bot does not keep
     * cookies. Throwing a validation exception puts the message beside the form
     * the way every other error appears.
     */
    private function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->rateLimitKey(), self::MAX_ATTEMPTS_PER_HOUR)) {
            return;
        }

        throw ValidationException::withMessages([
            'message' => __('You have sent several messages recently. Please try again later.'),
        ]);
    }

    /**
     * The rate limiter key for the current sender.
     */
    private function rateLimitKey(): string
    {
        return 'contact-form:'.request()->ip();
    }

    /**
     * Whether this submission shows the marks of an automated one.
     *
     * Timed with now() rather than the native time(), so the whole application
     * shares one clock: travel() in a test moves Carbon's and not PHP's, which
     * makes a native time() here untestable -- every test submitting instantly
     * trips the minimum-fill check and silently stores nothing.
     */
    private function looksAutomated(): bool
    {
        if ($this->website !== '') {
            return true;
        }

        // A renderedAt of 0 means the property never made it back, which is
        // itself not how a browser behaves.
        return $this->renderedAt === 0
            || (now()->getTimestamp() - $this->renderedAt) < self::MINIMUM_FILL_SECONDS;
    }

    /**
     * Email the business about a new submission.
     *
     * Sent on demand to a bare address with no account behind it, the same way
     * SendQueueFailureAlert reaches the operations address.
     *
     * A blank BusinessEmail sends nothing: that setting's own help text says a
     * blank value hides the address, so treating blank as "mail it anyway"
     * would contradict what the administrator was told it means.
     *
     * Any failure is logged and swallowed. The submission is already stored, so
     * an unreachable SMTP server must not turn a visitor's completed form into
     * an error page -- they would simply send it again, and the panel would
     * hold two copies of a message nobody has read.
     */
    private function notifyBusiness(ContactSubmission $submission): void
    {
        $recipient = app(Settings::class)->string(SettingKey::BusinessEmail);

        if ($recipient === '') {
            return;
        }

        try {
            Notification::route('mail', $recipient)
                ->notify(new ContactSubmissionReceived($submission));
        } catch (Throwable $exception) {
            Log::error('Failed to send contact form notification.', [
                'submission_id' => $submission->id,
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Clear the form and report success.
     *
     * The timestamp is reset along with the fields so the minimum-fill check
     * measures the new form rather than the old one, which would otherwise let
     * a second genuine message through unchecked.
     */
    private function finish(): void
    {
        $this->reset(['name', 'email', 'subject', 'message', 'website']);

        $this->renderedAt = now()->getTimestamp();
        $this->sent = true;

        Flux::toast(variant: 'success', text: __('Thank you. Your message has been sent.'));
    }

    /**
     * Render the form inside the public layout.
     *
     * Named explicitly because Livewire's own component_layout default is
     * 'layouts::app' -- the signed-in sidebar shell, which resolves
     * auth()->user()->avatarUrl() and so 500s for exactly the guests this page
     * exists for.
     *
     * The title and description are passed as layout params here rather than
     * through a #[Layout] attribute, because both come from a database row and
     * an attribute's arguments have to be constant expressions.
     */
    public function render(): View
    {
        $page = $this->page();

        return view('livewire.contact')
            ->layout('layouts::public', [
                'title' => $page instanceof Page ? $page->title : __('Contact'),
                'description' => $page instanceof Page ? $page->seo_description : null,
            ]);
    }
}
