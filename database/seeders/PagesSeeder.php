<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

class PagesSeeder extends Seeder
{
    /**
     * The public pages a fresh instance comes up with.
     *
     * The about and contact bodies are PLACEHOLDERS and say so. The privacy and
     * terms bodies are real prose rather than a checklist of headings, because a
     * fresh install should not put an obviously unfinished page in front of a
     * visitor -- but they describe only what THIS APPLICATION does by default
     * (accounts, a contact form, image uploads, session cookies) and assume the
     * deployment sells nothing and publishes no user content.
     *
     * That is a deliberate trade and it has a cost: text that reads as finished
     * is text somebody may deploy unread. Each of the two therefore opens with a
     * blockquote saying it is not legal advice and naming the assumptions it
     * makes, and neither can be correct about jurisdiction, processors or
     * retention for a deployment nobody has looked at. Do not remove those
     * warnings, and do not extend this copy to cover payment, advertising or
     * user-published content -- a boilerplate cannot know any of it, and the
     * more complete the text looks the less likely it is to be reviewed.
     *
     * Published on purpose, unlike the model's own default. A footer linking to
     * four pages that all 404 is the state a fresh install must not come up in,
     * and every body here is written to be safe to have live while it is being
     * replaced.
     *
     * The contact page is the one exception to show_in_footer: the footer links
     * to route('contact') itself, unconditionally, so linking the page row too
     * would print "Contact" twice.
     *
     * @var list<array{slug: string, title: string, sort_order: int, show_in_footer: bool, seo_description: string, body: string}>
     */
    private const array PAGES = [
        [
            'slug' => 'about',
            'title' => 'About',
            'show_in_footer' => true,
            'sort_order' => 10,
            'seo_description' => 'Who we are and what we do. Replace this text from the admin panel.',
            'body' => <<<'MARKDOWN'
            **This is placeholder copy.** Replace it from the admin panel under Content → Pages.

            ## Who we are

            Describe the business here: what it does, who it does it for, and how long it has been
            doing it. A paragraph or two is plenty — this page exists so a visitor can confirm they
            are dealing with a real organisation.

            ## What we do

            A short list works well here:

            - The first thing you do
            - The second thing you do
            - The thing people actually come to you for

            ## Getting in touch

            Point people at the contact form, and repeat the address or phone number if the footer
            does not already carry it.
            MARKDOWN,
        ],
        [
            'slug' => 'contact',
            'title' => 'Contact',
            'show_in_footer' => false,
            'sort_order' => 20,
            'seo_description' => 'Get in touch with us. Replace this text from the admin panel.',
            'body' => <<<'MARKDOWN'
            **This is placeholder copy.** Replace it from the admin panel under Content → Pages.

            Use the form below to send a message and we will reply as soon as we can. If your
            question is urgent, the phone number and postal address in the footer are the faster
            route.

            Mention any reference or account number you already have — it saves a round trip.
            MARKDOWN,
        ],
        [
            'slug' => 'privacy',
            'title' => 'Privacy Policy',
            'show_in_footer' => true,
            'sort_order' => 30,
            'seo_description' => 'How we collect, use and protect your personal information.',
            'body' => <<<'MARKDOWN'
            > **This is placeholder copy, not legal advice.** It describes what this application
            > does by default, which is probably not everything your deployment does. Check every
            > section against what you actually collect, and have somebody qualified review it
            > before you rely on it. Requirements differ by jurisdiction — UK GDPR, EU GDPR and
            > CCPA all differ.
            >
            > Two gaps to close before you rely on this. "How long we keep it" names no retention
            > periods, because the application enforces none — most jurisdictions expect specific
            > periods, so set yours once you know them. And deleting an account removes the
            > account and nothing else: contact-form messages are stored unlinked to it and
            > survive, so removing one is a manual job until you build something that does it.

            We collect as little as we can, use it only to run this service, and never sell it.

            ## What we collect

            - **Account details.** Your name and email address when you register, and a password we
              store only as a hash we cannot reverse.
            - **Messages you send us.** Anything you put in the contact form, including your name
              and email address so we can reply.
            - **Images you upload.** Files you add to your account, such as a profile picture.
            - **Technical logs.** Our servers record IP addresses, browser type and the pages
              requested, which we use to keep the site working and to investigate abuse.

            ## Why we collect it

            To give you an account and let you sign in, to answer the messages you send us, to keep
            the service secure and available, and to meet our legal obligations. Where the law
            requires a lawful basis, ours is the performance of our agreement with you and our
            legitimate interest in running a secure service.

            ## How long we keep it

            Account details for as long as your account exists. Deleting your account removes them
            straight away.

            Messages you send through the contact form are stored separately and are not deleted
            with your account. We keep them, and our technical logs, until they are no longer
            needed for the purposes above. Ask us if you want a message removed.

            ## Who we share it with

            Only the suppliers who help us run the service — hosting, email delivery and error
            monitoring — and only what they need. We do not sell your data or share it for
            advertising. We may disclose information if the law requires it.

            ## Cookies

            We set a session cookie to keep you signed in and a cookie that protects forms against
            cross-site request forgery. Both are necessary for the site to work and neither is used
            for tracking or advertising.

            ## Your rights

            You may ask us for a copy of your data, correct it, delete it, or object to how we use
            it. You can change your details or delete your account yourself from your account
            settings. To ask for anything else, use the contact form. If you think we have handled
            your data badly, you may complain to your data protection regulator.

            ## Changes to this policy

            If we change how we handle your data we will update this page and change the date below.

            ## Contact us

            Questions about this policy can go through the contact form.
            MARKDOWN,
        ],
        [
            'slug' => 'terms',
            'title' => 'Terms & Conditions',
            'show_in_footer' => true,
            'sort_order' => 40,
            'seo_description' => 'The terms that apply to using this site and our services.',
            'body' => <<<'MARKDOWN'
            > **This is placeholder copy, not legal advice.** It is a short, general set of terms
            > that will not fit every deployment — it assumes nothing is sold and no content is
            > published by users. Check every section, and have somebody qualified review it before
            > you rely on it. The limitation of liability in particular is where template wording
            > most often fails to do what its author assumed.

            By using this site you agree to these terms. If you do not agree, please do not use it.

            ## Your account

            You need to be old enough to enter a contract where you live. Keep your password to
            yourself — you are responsible for what happens under your account. Tell us promptly if
            you think someone else has access to it. We may suspend or close an account that breaks
            these terms.

            ## Acceptable use

            Do not use this site to break the law, to harass anyone, or to send anything malicious.
            Do not try to gain access to parts of the service that are not yours, interfere with how
            it runs, or collect data from it automatically without our permission.

            ## Your content

            Anything you upload stays yours. You give us permission to store and display it only so
            far as we need to in order to run the service. We may remove content that breaks these
            terms. Do not upload anything you do not have the right to.

            ## Availability

            We try to keep the service running but we do not promise it will always be available or
            error-free. It is provided as-is. We may change or withdraw features, and we will give
            notice of significant changes where we reasonably can.

            ## Our liability

            Nothing here limits liability for death or personal injury caused by our negligence, for
            fraud, or for anything else that cannot legally be excluded. Beyond that, we are not
            liable for indirect or consequential loss, or for loss of profit, business or data.

            ## Ending it

            You may close your account at any time from your account settings. We may end your
            access if you break these terms. Sections that by their nature should survive — such as
            the limits on liability — continue to apply afterwards.

            ## Changes to these terms

            We may update these terms. If we make a significant change we will say so on this page
            and update the date below. Continuing to use the site means accepting the new version.

            ## Contact us

            Questions about these terms can go through the contact form.
            MARKDOWN,
        ],
    ];

    /**
     * Seed the public pages.
     *
     * Built with `new Page` and explicit assignment rather than the factory:
     * factories call fake(), which is a require-dev package absent from the
     * --no-dev production image the container entrypoint seeds inside. See
     * .ai/rules/seeders.md -- a factory here crashloops the container.
     *
     * firstOrCreate, so re-seeding on every deploy never overwrites the real
     * policy text an operator has since written. That is the same reason
     * SettingsSeeder uses it, and it matters more here: this copy is the part
     * somebody paid a solicitor for.
     */
    public function run(): void
    {
        foreach (self::PAGES as $attributes) {
            Page::firstOrCreate(
                ['slug' => $attributes['slug']],
                [
                    'title' => $attributes['title'],
                    'body' => $attributes['body'],
                    'seo_description' => $attributes['seo_description'],
                    'is_published' => true,
                    'show_in_footer' => $attributes['show_in_footer'],
                    'sort_order' => $attributes['sort_order'],
                ],
            );
        }
    }
}
