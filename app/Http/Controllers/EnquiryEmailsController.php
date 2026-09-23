<?php

namespace App\Http\Controllers;

use App\Models\Enquiry;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The "stop the follow-up emails" link at the foot of every sequence email.
 *
 * Signed, so only the customer who received the email can use it. The GET
 * only shows a confirmation button and the POST does the work, because mail
 * scanners follow links in emails -- a GET that unsubscribed would end
 * sequences nobody asked to end.
 */
class EnquiryEmailsController extends Controller
{
    public function show(Request $request, Enquiry $enquiry): View
    {
        return view('enquiry-emails', ['enquiry' => $enquiry, 'stopped' => false, 'action' => $request->fullUrl()]);
    }

    public function stop(Request $request, Enquiry $enquiry): View
    {
        $enquiry->forceFill(['next_follow_up_at' => null])->save();

        return view('enquiry-emails', ['enquiry' => $enquiry, 'stopped' => true, 'action' => $request->fullUrl()]);
    }
}
