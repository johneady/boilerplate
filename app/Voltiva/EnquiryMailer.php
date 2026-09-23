<?php

namespace App\Voltiva;

use App\Models\Enquiry;
use App\Notifications\EnquiryFollowUp;
use App\Notifications\EnquiryReceived;
use App\Settings\SettingKey;
use App\Settings\Settings;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Sends the emails around an enquiry: the alert to the sales team, and the
 * customer's automatic sequence.
 *
 * Every send is wrapped so a mail failure is logged rather than thrown. The
 * enquiry is already stored when these run, so an unreachable SMTP server must
 * not turn a customer's completed form into an error page -- and a sequence
 * email that failed is retried by the next scheduled run, because the step is
 * only advanced after a successful send.
 */
class EnquiryMailer
{
    public function __construct(private readonly Settings $settings) {}

    /**
     * Handle a newly stored enquiry: alert the team and send the customer the
     * first email of the sequence.
     */
    public function received(Enquiry $enquiry): void
    {
        $this->alertTeam($enquiry);
        $this->sendNext($enquiry);
    }

    /**
     * Send every sequence email that has fallen due. Returns how many went out.
     */
    public function sendDue(): int
    {
        $sent = 0;

        // By id rather than each(): each() pages with OFFSET, and sending an
        // email moves the enquiry's next_follow_up_at out of the due set --
        // so every page would start further along a shrinking result and
        // silently skip enquiries once more than one page is due.
        Enquiry::query()
            ->followUpDue()
            ->with('vehicle')
            ->lazyById(200)
            ->each(function (Enquiry $enquiry) use (&$sent): void {
                // A closed enquiry that still has a date set (closed before
                // this feature stopped it, or edited directly) ends here.
                if (! $enquiry->status->receivesFollowUps()) {
                    $enquiry->forceFill(['next_follow_up_at' => null])->save();

                    return;
                }

                if ($this->sendNext($enquiry)) {
                    $sent++;
                }
            });

        return $sent;
    }

    /**
     * Send the enquiry's next sequence email, in the customer's language.
     */
    public function sendNext(Enquiry $enquiry): bool
    {
        $step = $enquiry->follow_up_step + 1;

        if ($step > Enquiry::followUpCount()) {
            $enquiry->forceFill(['next_follow_up_at' => null])->save();

            return false;
        }

        try {
            Notification::route('mail', $enquiry->email)
                ->notify((new EnquiryFollowUp($enquiry, $step))->locale($enquiry->locale));
        } catch (Throwable $exception) {
            Log::error('Failed to send enquiry follow-up email.', [
                'enquiry_id' => $enquiry->id,
                'step' => $step,
                'exception' => $exception->getMessage(),
            ]);

            return false;
        }

        $enquiry->recordFollowUpSent($step);

        return true;
    }

    /**
     * Email the sales team. A blank BusinessEmail sends nothing, as for the
     * contact form: that setting's help text says blank hides the address.
     */
    private function alertTeam(Enquiry $enquiry): void
    {
        $recipient = $this->settings->string(SettingKey::BusinessEmail);

        if ($recipient === '') {
            return;
        }

        try {
            // In the team's language, not the customer's: this runs inside the
            // customer's request, whose locale is whatever they picked in the
            // switcher. See config('voltiva.team_locale').
            Notification::route('mail', $recipient)
                ->notify((new EnquiryReceived($enquiry))->locale((string) config('voltiva.team_locale')));
        } catch (Throwable $exception) {
            Log::error('Failed to send new enquiry alert.', [
                'enquiry_id' => $enquiry->id,
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
