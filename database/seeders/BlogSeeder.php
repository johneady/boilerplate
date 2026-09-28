<?php

namespace Database\Seeders;

use App\Media\MediaCollection;
use App\Media\MediaManager;
use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use App\Settings\SettingKey;
use App\Settings\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Intervention\Image\Geometry\Factories\RectangleFactory;
use Intervention\Image\ImageManager;

/**
 * Sample blog content, so a demo instance shows a working blog the moment
 * the module is switched on -- a populated index, archives, a feed, and an
 * editorial mix of published, scheduled and drafted posts.
 *
 * DatabaseSeeder runs this only where the quick dev logins are offered (see
 * PlanSeeder for the same gate), so a production install never starts with
 * posts it did not write. Like PlanSeeder it is invisible until the blog is
 * switched on, and like every seeder that runs inside the --no-dev image it
 * builds its rows with `new Post` rather than a factory (.ai/rules/seeders.md).
 *
 * Each post also gets a generated cover image -- a deterministic gradient
 * derived from its slug -- attached through MediaManager so it goes through
 * the same validation, re-encoding and conversions as a cover uploaded from
 * the admin panel. Deterministic (no mt_rand for the colours) so two seeds
 * of the same demo agree, and because there is nothing to gain from random
 * art. Skipped in the test environment, where it costs real image decoding
 * time on every suite run; enable withCovers to exercise it deliberately.
 */
class BlogSeeder extends Seeder
{
    public bool $withCovers = true;

    /**
     * Covers already drawn this run, encoded, keyed by palette: sixteen posts
     * share six palettes, and the same palette always draws the same image.
     *
     * @var array<int, string>
     */
    private array $covers = [];

    /**
     * The categories, seeded first so posts can point at them.
     *
     * @var array<int, array{name: string, slug: string}>
     */
    private const array CATEGORIES = [
        ['name' => 'Company news', 'slug' => 'company-news'],
        ['name' => 'Product', 'slug' => 'product'],
        ['name' => 'Guides', 'slug' => 'guides'],
        ['name' => 'Behind the scenes', 'slug' => 'behind-the-scenes'],
    ];

    /**
     * The tags posts may carry, keyed by slug.
     *
     * @var array<string, string>
     */
    private const array TAGS = [
        'announcements' => 'Announcements',
        'releases' => 'Releases',
        'how-to' => 'How-to',
        'tips' => 'Tips',
        'admin' => 'Admin',
        'payments' => 'Payments',
        'content' => 'Content',
        'demo' => 'Demo',
    ];

    /**
     * The gradient palettes a generated cover picks between, as RGB pairs.
     *
     * @var array<int, array{0: array{int, int, int}, 1: array{int, int, int}}>
     */
    private const array COVER_PALETTES = [
        [[63, 131, 248], [176, 127, 245]],   // blue to violet
        [[13, 148, 136], [59, 130, 246]],    // teal to blue
        [[217, 119, 6], [239, 68, 68]],      // amber to red
        [[16, 185, 129], [104, 117, 245]],   // emerald to indigo
        [[236, 72, 153], [99, 102, 241]],    // pink to indigo
        [[249, 115, 22], [234, 179, 8]],     // orange to yellow
    ];

    /**
     * The posts, newest first in the list only for the reader's convenience.
     *
     * `published` is days from now: negative for a past publish date, positive
     * for a scheduled one, null for a draft. `time` is the time of day each
     * went live, so the demo's timestamps read as publishing rather than as a
     * seeder that ran at 03:14.
     *
     * @var array<int, array{slug: string, title: string, category: string, tags: list<string>, author: string, published: int|null, time: string, seo_description: string, body: string}>
     */
    private const array POSTS = [
        [
            'slug' => 'writing-content-pages-that-pull-their-weight',
            'title' => 'Writing content pages that pull their weight',
            'category' => 'guides',
            'tags' => ['content', 'how-to'],
            'author' => 'editor@example.com',
            'published' => -10,
            'time' => '10:00',
            'seo_description' => 'A short guide to writing the pages every site needs and nobody wants to draft twice.',
            'body' => <<<'MD'
Every site needs the same handful of pages, and nobody enjoys writing them. Here is the approach that gets them done without the usual staring at a blank editor.

## Start with the reader who is about to leave

The privacy policy is read by someone deciding whether to trust you. The terms are read by someone deciding whether to sign. The about page is read by someone deciding whether to email. Write the sentence that answers the decision first, and put the qualifications after it.

## Keep each page to one job

A page that explains your refunds AND your shipping AND your values is a page nobody finishes. Split it. A short page that gets read beats a complete page that does not.

## Draft in Markdown, publish from the panel

- Write the draft anywhere you like -- Markdown is plain text.
- Paste it into the page editor and add the search description.
- Leave it unpublished and preview it by URL first.
- Publish when it reads well, not when it exists.

The search description matters more than it looks: it is the sentence shown under the link in search results and every chat app that unfurls it. One clear sentence, not a keyword list.
MD,
        ],
        [
            'slug' => 'payments-without-the-paperwork',
            'title' => 'Payments without the paperwork',
            'category' => 'product',
            'tags' => ['payments', 'releases'],
            'author' => 'manager@example.com',
            'published' => -25,
            'time' => '14:30',
            'seo_description' => 'Payment links, subscriptions, receipts and refunds -- an overview of what the payments module does.',
            'body' => <<<'MD'
Taking money online is not one feature, it is five: a checkout, a receipt, a refund path, a reconciliation story, and the bookkeeping to hold them together. This is a tour of how the pieces fit here.

## Payment links

A payment link is a URL you send a customer: one-off or reusable, a fixed amount or let-the-customer-choose. Whoever opens it gets a checkout in the currency you configured.

## Subscriptions

Plans carry monthly and yearly prices, free trials and a grace period for failed renewals. A subscriber manages their own subscription from their settings page -- cancel, resume, the lot.

## Receipts and refunds

Every payment gets a numbered receipt PDF, emailed automatically and re-downloadable from a signed link. Refunds return to the original payment method and the ledger records both halves.

## The demo gateway

For demonstrations there is a pretend gateway that takes no money and needs no credentials -- approve and decline buttons that behave like a real hosted checkout, so the whole flow can be shown with nothing at stake.
MD,
        ],
        [
            'slug' => 'how-we-approach-database-seeding',
            'title' => 'How we approach database seeding',
            'category' => 'behind-the-scenes',
            'tags' => ['demo', 'tips'],
            'author' => 'admin@example.com',
            'published' => -40,
            'time' => '09:00',
            'seo_description' => 'Why the demo data is replayed through real actions rather than inserted as rows, and what that buys.',
            'body' => <<<'MD'
There are two ways to fill a demo database: insert the finished rows, or replay the actions that produce them. The difference shows up the first time somebody asks "what happens if I click this?"

## Rows lie convincingly

A row can be inserted in a state no code path would ever produce -- a payment that was never charged, a subscription with no customer. It looks right on the dashboard and falls over the moment a real action touches it.

## Replay instead

The demo data here is a schedule of events -- sign-ups, checkouts, renewals, refunds -- sorted by time and replayed through the same actions the app uses. If the app has a bug in a renewal, the seeder hits it too.

## Deterministic, and idempotent

The random draws are seeded, so the same demo comes out the same every time. And a re-seed checks whether the data already exists before writing anything, so a redeploy never resets an operator's edits.

## The rule we ended with

If a seeder needs to know something the application itself does not know, the seeder is wrong. Delete the shortcut and call the real code.
MD,
        ],
        [
            'slug' => 'getting-started-with-roles-and-permissions',
            'title' => 'Getting started with roles and permissions',
            'category' => 'guides',
            'tags' => ['admin', 'how-to'],
            'author' => 'editor@example.com',
            'published' => -55,
            'time' => '11:15',
            'seo_description' => 'The five roles, what each can do, and how to hand someone exactly as much access as they need.',
            'body' => <<<'MD'
Every panel needs an answer to "who may do what", and the honest answer is usually "it depends who". Here is the model this application ships with.

## The five roles

- **User** -- signs in, manages their own account. No panel access.
- **Editor** -- content: pages, the blog, uploaded files, contact messages.
- **Bookkeeper** -- reads the money: payments, refunds, subscriptions, disputes.
- **Manager** -- day-to-day operations: everything an Editor and a Bookkeeper do, plus acting on payments and editing anyone's posts.
- **Administrator** -- everything, including settings and roles.

## Permissions, not role names

Code never asks "is this user an Editor". It asks "may this user update pages". Roles grant permissions; the grants are the thing the application checks. The practical difference: the day a sixth role appears, nothing that checks permissions needs to change.

## Handing out the least

Start a new staff member on the least role that covers their job. Upgrading is one field. The alternative -- starting everyone as a Manager and meaning to walk it back -- never gets walked back.
MD,
        ],
        [
            'slug' => 'a-tour-of-the-admin-panel',
            'title' => 'A tour of the admin panel',
            'category' => 'product',
            'tags' => ['admin', 'tips'],
            'author' => 'manager@example.com',
            'published' => -75,
            'time' => '15:45',
            'seo_description' => 'Where everything lives in the admin panel, from content to settings to the diagnostics that tell you the state of things.',
            'body' => <<<'MD'
The admin panel is organised the way the work is organised: content together, money together, system things off to the side.

## Content

Pages, posts, categories, tags, the media library and contact messages share one navigation group. An editor never needs to leave it.

## Payments

Payment records, subscriptions, plans, payment links, disputes, tax rates and webhook deliveries -- each visible only while payments are switched on. Turn the module off and the whole group disappears from the navigation.

## System

Settings, the audit log, the log viewer and the server report. The settings page is one screen with tabs: business details, brand, the blog switch, registration, email, locale, payments.

## Diagnostics

The settings page also carries a diagnostics report -- configuration this application can check about itself, and the state of the payment gateways. It is the first place to look when something behaves differently in production.
MD,
        ],
        [
            'slug' => 'why-every-demo-starts-with-real-data',
            'title' => 'Why every demo starts with real data',
            'category' => 'product',
            'tags' => ['demo', 'announcements'],
            'author' => 'admin@example.com',
            'published' => -95,
            'time' => '09:30',
            'seo_description' => 'An empty demo answers no questions. Here is why the boilerplate seeds a year of activity before anybody logs in.',
            'body' => <<<'MD'
An empty application answers no questions. "What does a refund look like?" cannot be shown on a payment that does not exist, and nobody sits through a demo while somebody hunts for data to click.

## The year of trading

On install, the demo seeds a year of activity: customers, one-off payments, subscriptions with their renewals and one failure, a refund, an open dispute, and a stack of contact messages. The dashboard has a revenue chart because there is revenue to chart.

## Real actions, real state

Every one of those records was produced by the same code a real customer would drive. Nothing was inserted as a "realistic-looking" row.

## Yours to delete

The demo data is data. Delete the customers, the payments, the works -- and the application keeps working, because none of the application's behaviour depends on it.

That is the point: the demo shows the application, and the data is just the set dressing.
MD,
        ],
        [
            'slug' => 'what-we-ship-on-fridays',
            'title' => 'What we ship on Fridays: the changelog habit',
            'category' => 'company-news',
            'tags' => ['announcements', 'tips'],
            'author' => 'editor@example.com',
            'published' => -120,
            'time' => '16:00',
            'seo_description' => 'A small ritual that keeps releases honest: every Friday, a changelog entry, however small.',
            'body' => <<<'MD'
Ship something every Friday and write three lines about it. That is the whole ritual, and it has survived every reorganisation of the calendar.

## Why the changelog entry matters more than the shipping

The entry forces the question the release alone dodges: what actually changed for the person using this? A dependency bump that changes nothing for them is an entry that says so. If the entry will not write, the change was not worth shipping.

## Why Friday

Because the deadline is arbitrary, which is what makes it real. A feature ships when it is done; the changelog ships on Friday, done or not, which quietly converts "almost done" into "done".

## What it looks like

- Fixed: the invoice total rounding down on amounts under a dollar.
- Added: a blog module, switchable from the settings page.
- Changed: receipts now show the business phone when one is set.

Three lines. Every week. The archive of them becomes the roadmap you actually followed.
MD,
        ],
        [
            'slug' => 'introducing-the-boilerplate',
            'title' => 'Introducing the boilerplate',
            'category' => 'company-news',
            'tags' => ['announcements', 'releases'],
            'author' => 'admin@example.com',
            'published' => -150,
            'time' => '08:30',
            'seo_description' => 'Why we built a starting point with the boring parts already done: accounts, content, payments and the operational spine between them.',
            'body' => <<<'MD'
Every project starts the same way: authentication, a content page or two, an admin panel, and a fortnight gone before the interesting part begins. This is the starting point that gets that fortnight back.

## What is in the box

- Accounts with everything a real product needs: email verification, password reset, two-factor, passkeys.
- A roles-and-permissions model that covers a small team on day one.
- Content pages and a blog, written in Markdown from the admin panel.
- Payments: one-off and subscription, with receipts and refunds.
- The operational spine -- logging, audits, health checks, summaries.

## What is deliberately not in the box

Opinions about your domain. The boilerplate ends where your product begins, and not a screen later.

## The demo instance

Deploy it and it seeds itself into a working state: accounts you can one-click into, a year of trading on the dashboard, and content on every page. It is meant to be shown, not assembled first.

Start from it, and put your fortnight into the thing only you can build.
MD,
        ],
        [
            'slug' => 'the-q4-maintenance-window',
            'title' => 'The Q4 maintenance window',
            'category' => 'company-news',
            'tags' => ['announcements'],
            'author' => 'admin@example.com',
            'published' => 3,
            'time' => '06:00',
            'seo_description' => 'Scheduled maintenance later this quarter: what changes, what to expect, and what nothing will notice.',
            'body' => <<<'MD'
This post is scheduled: it goes live on its publish date without anybody pressing anything, which is the feature it is demonstrating.

## What to expect

Nothing dramatic. A maintenance window is the boring kind of announcement: a short period where the application may be unavailable while routine work happens underneath it.

## What will not change

Your data, your settings, your links. The work is underneath all of those.

## Why announce it at all

Because a status page that only ever says "all systems operational" is a status page nobody believes. Announcing the boring work is what makes the silence between announcements mean something.

If anything does go sideways, updates land here first.
MD,
        ],
        [
            'slug' => 'notes-on-the-next-release',
            'title' => 'Notes on the next release',
            'category' => 'behind-the-scenes',
            'tags' => ['releases', 'demo'],
            'author' => 'admin@example.com',
            'published' => null,
            'time' => '13:00',
            'seo_description' => 'A working draft -- the notes for the next release, still being written.',
            'body' => <<<'MD'
This post is a draft. It is invisible to the public, and anyone who can edit posts can preview it at its URL -- which is how this paragraph is reaching you.

## In progress

- The changelog for the release.
- A migration note for anybody with a custom theme.

## Not making it

The export redesign. It needs its own release and its own changelog entry, and pretending otherwise is how releases end up late.

A draft is a promise to a schedule, not to the public: this goes out when the list above is two items shorter.
MD,
        ],
    ];

    /**
     * Seed the sample content into an empty blog.
     *
     * Any existing post means the blog is somebody's own -- so a redeploy
     * (which re-runs the seeders) is a no-op after the first seed, leaving
     * administrators' edits alone, and the sample content can be deleted
     * without it creeping back.
     */
    public function run(): void
    {
        // Production never gets sample content, however the seeder is
        // reached: DatabaseSeeder gates this call already, and this stands
        // behind it the way DemoBusinessSeeder does.
        if (app()->environment('production')) {
            return;
        }

        $this->switchOnBlog();

        if (Post::query()->exists()) {
            return;
        }

        $withCovers = $this->withCovers && ! app()->environment('testing');

        DB::transaction(function () use ($withCovers): void {
            $categories = $this->seedCategories();
            $tags = $this->seedTags();

            foreach (self::POSTS as $definition) {
                $post = new Post;

                $post->title = $definition['title'];
                $post->slug = $definition['slug'];
                $post->body = $definition['body'];
                $post->seo_description = $definition['seo_description'];
                $post->published_at = $this->publishDate($definition['published'], $definition['time']);
                $post->author_id = User::query()->where('email', $definition['author'])->value('id');
                $post->category_id = $categories[$definition['category']]->id;

                $post->save();

                $post->tags()->sync(array_map(
                    fn (string $slug): int => $tags[$slug]->id,
                    $definition['tags'],
                ));

                if ($withCovers) {
                    $this->attachGeneratedCover($post);
                }
            }
        });
    }

    /**
     * Switch the blog on for the instance being seeded, unless its operator
     * has already chosen.
     *
     * The same stance DemoBusinessSeeder takes for payments: a demo instance
     * should show a working blog the moment it comes up, but a saved choice
     * -- including an explicit "off" -- is never overwritten. Skipped in the
     * test environment, where the suite expects the module's default state.
     */
    private function switchOnBlog(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        $settings = app(Settings::class);

        if ($settings->has(SettingKey::BlogEnabled)) {
            return;
        }

        $settings->setMany([SettingKey::BlogEnabled->value => true]);
    }

    /**
     * @return array<string, Category>
     */
    private function seedCategories(): array
    {
        $categories = [];

        foreach (self::CATEGORIES as $definition) {
            $categories[$definition['slug']] = Category::query()->firstOrCreate(
                ['slug' => $definition['slug']],
                ['name' => $definition['name']],
            );
        }

        return $categories;
    }

    /**
     * @return array<string, Tag>
     */
    private function seedTags(): array
    {
        $tags = [];

        foreach (self::TAGS as $slug => $name) {
            $tags[$slug] = Tag::query()->firstOrCreate(
                ['slug' => $slug],
                ['name' => $name],
            );
        }

        return $tags;
    }

    /**
     * The publish date a post definition describes, in the display timezone
     * the rest of the application stores UTC in.
     *
     * @param  int|null  $daysFromNow  Negative for the past, positive for scheduled, null for a draft.
     */
    private function publishDate(?int $daysFromNow, string $time): ?CarbonImmutable
    {
        if ($daysFromNow === null) {
            return null;
        }

        [$hour, $minute] = array_map('intval', explode(':', $time));

        // The clock time is read in the display timezone and converted back,
        // so "09:00" shows as 09:00 in the panel and on the site rather than
        // as 09:00 UTC shifted by the installation's offset.
        return CarbonImmutable::now(app(Settings::class)->string(SettingKey::Timezone))
            ->addDays($daysFromNow)
            ->setTime($hour, $minute)
            ->setTimezone((string) config('app.timezone'));
    }

    /**
     * Attach a generated gradient cover through MediaManager.
     */
    private function attachGeneratedCover(Post $post): void
    {
        $palette = crc32($post->slug) % count(self::COVER_PALETTES);

        // A real temp path rather than one in the storage disks: the file is
        // an input, not stored state, and MediaManager::attach() stages it
        // itself on the private disk before queueing the re-encode.
        $tempPath = tempnam(sys_get_temp_dir(), 'blog-cover');

        if ($tempPath === false) {
            return;
        }

        // attach() copies the file onto the private disk rather than moving
        // it, so the temp file is removed here or it outlives every seed.
        try {
            file_put_contents($tempPath, $this->covers[$palette] ??= $this->drawCover(...self::COVER_PALETTES[$palette]));

            app(MediaManager::class)->attach(
                file: new UploadedFile($tempPath, $post->slug.'-cover.png', 'image/png', null, true),
                collection: MediaCollection::PostCover,
                owner: $post,
            );
        } finally {
            @unlink($tempPath);
        }
    }

    /**
     * Draw a 1600x900 vertical gradient between two colours, as a PNG.
     *
     * Built with Intervention Image, a runtime dependency, so this runs
     * inside the --no-dev production image too. Each run of rows that rounds
     * to the same colour is drawn as one full-width band -- about a hundred
     * draws rather than one per row -- and drawn at full size, because
     * stretching a narrower image up costs more than drawing it.
     *
     * @param  array{int, int, int}  $from
     * @param  array{int, int, int}  $to
     */
    private function drawCover(array $from, array $to): string
    {
        /** @var list<array{colour: string, top: int, height: int}> $bands */
        $bands = [];

        for ($y = 0; $y < 900; $y++) {
            $position = $y / 899;
            $colour = sprintf(
                '#%02x%02x%02x',
                (int) round($from[0] + ($to[0] - $from[0]) * $position),
                (int) round($from[1] + ($to[1] - $from[1]) * $position),
                (int) round($from[2] + ($to[2] - $from[2]) * $position),
            );

            if ($bands !== [] && $bands[array_key_last($bands)]['colour'] === $colour) {
                $bands[array_key_last($bands)]['height']++;
            } else {
                $bands[] = ['colour' => $colour, 'top' => $y, 'height' => 1];
            }
        }

        /** @var string $driver */
        $driver = config('images.driver');

        $gradient = ImageManager::usingDriver($driver)->createImage(1600, 900);

        foreach ($bands as $band) {
            $gradient->drawRectangle(function (RectangleFactory $rectangle) use ($band): void {
                $rectangle->at(0, $band['top']);
                $rectangle->size(1600, $band['height']);
                $rectangle->background($band['colour']);
            });
        }

        return (string) $gradient->encodeUsingFileExtension('png');
    }
}
