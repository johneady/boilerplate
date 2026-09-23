<?php

namespace App\Console\Commands;

use App\Voltiva\EnquiryMailer;
use Illuminate\Console\Command;

/**
 * Sends the follow-up emails that have fallen due in each open enquiry's
 * automatic sequence (day 2, 5, 10 and 20 after it arrived).
 */
class SendEnquiryFollowUps extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-enquiry-follow-ups';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send the automatic follow-up emails that are due for open enquiries';

    /**
     * Execute the console command.
     */
    public function handle(EnquiryMailer $mailer): int
    {
        $sent = $mailer->sendDue();

        $this->components->info("Sent {$sent} follow-up email(s).");

        return self::SUCCESS;
    }
}
