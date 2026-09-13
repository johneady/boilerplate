<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

class PagesSeeder extends Seeder
{
    /**
     * The public pages a fresh instance comes up with.
     *
     * The bodies are PLACEHOLDERS and say so in their own text. Real privacy and
     * terms copy depends on the jurisdiction, what data the deployment actually
     * collects and whether it takes payment -- none of which a boilerplate can
     * know. Shipping confident-sounding fake policy text would be worse than
     * shipping none: somebody would deploy it unread.
     *
     * Published on purpose, unlike the model's own default. A footer linking to
     * four pages that all 404 is the state a fresh install must not come up in,
     * and the placeholder copy is written to be safe to have live while it is
     * being replaced.
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
            > **This is placeholder copy, not legal advice.** It is a checklist of the sections a
            > privacy policy usually needs, not a policy. Replace every section below with text that
            > describes what this deployment actually does, and have somebody qualified review it
            > before you rely on it. Requirements differ by jurisdiction (UK GDPR, EU GDPR, CCPA and
            > others all differ) and by what data you collect.

            ## What information we collect

            List it plainly: names and email addresses from account registration and the contact
            form, and whatever your analytics, payment processor or hosting logs record.

            ## Why we collect it

            Give the purpose and, where your jurisdiction requires one, the lawful basis for each
            kind of data.

            ## How long we keep it

            State a retention period, or the rule that determines one. "As long as necessary" on its
            own is not an answer regulators accept.

            ## Who we share it with

            Name the categories of processor you actually use — hosting, email delivery, payment,
            analytics — and say whether any of them are outside your own country.

            ## Cookies

            Say which cookies the site sets and what they do. Session and authentication cookies
            usually need only a mention; analytics and advertising cookies generally need consent.

            ## Your rights

            Set out the rights people have over their data — access, correction, deletion,
            portability, objection — and exactly how to exercise them.

            ## Changes to this policy

            Explain how you will announce changes, and keep the "last updated" date accurate.

            ## Contact us

            Give the address for data protection enquiries, and the supervisory authority people can
            complain to if your jurisdiction has one.
            MARKDOWN,
        ],
        [
            'slug' => 'terms',
            'title' => 'Terms & Conditions',
            'show_in_footer' => true,
            'sort_order' => 40,
            'seo_description' => 'The terms that apply to using this site and our services.',
            'body' => <<<'MARKDOWN'
            > **This is placeholder copy, not legal advice.** It is a checklist of the sections terms
            > of service usually need, not a contract. Replace every section below with text that
            > describes what this deployment actually offers, and have somebody qualified review it
            > before you rely on it.

            ## Agreement to these terms

            State that using the site means accepting these terms, and who "we" and "you" refer to.

            ## Accounts

            Cover eligibility, that people are responsible for their own credentials, and the
            grounds on which you may suspend or close an account.

            ## Acceptable use

            List what people may not do: breaking the law, interfering with the service, scraping,
            attempting unauthorised access, uploading anything malicious.

            ## Your content

            If people can submit content, say who owns it, what licence you need in order to host
            and display it, and what you may remove.

            ## Payment

            Delete this section if nothing is sold. Otherwise cover prices, billing cycles,
            renewals, refunds and cancellation.

            ## Availability

            Explain that the service is provided as-is, and be honest about whether you offer any
            uptime commitment.

            ## Limitation of liability

            This section in particular is where template wording most often fails to do what its
            author assumed. Have it reviewed.

            ## Termination

            Say how either side ends the arrangement, and what happens to data afterwards.

            ## Governing law

            Name the jurisdiction whose law applies and where disputes are heard.

            ## Contact us

            Give an address for questions about these terms.
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
