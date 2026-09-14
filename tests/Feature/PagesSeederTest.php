<?php

use App\Models\Page;
use Database\Seeders\PagesSeeder;

test('the seeder publishes the four public pages', function () {
    $this->seed(PagesSeeder::class);

    expect(Page::pluck('slug')->all())
        ->toEqualCanonicalizing(['about', 'contact', 'privacy', 'terms']);
});

test('the policy pages keep the not-legal-advice warning', function () {
    $this->seed(PagesSeeder::class);

    // The privacy and terms bodies read as finished prose rather than as a
    // checklist of headings, which is exactly why this warning has to stay:
    // text that looks complete is text somebody deploys unread. Losing it is a
    // silent failure -- the page still renders and still looks right.
    foreach (['privacy', 'terms'] as $slug) {
        $page = Page::whereSlug($slug)->sole();

        expect($page->body)->toContain('**This is placeholder copy, not legal advice.**')
            ->and($page->renderedBody())->toContain('not legal advice');
    }
});

test('re-seeding never overwrites policy text somebody has replaced', function () {
    $this->seed(PagesSeeder::class);

    $privacy = Page::whereSlug('privacy')->sole();
    $privacy->update(['body' => 'Our actual reviewed privacy policy.']);

    // This is the copy somebody paid a solicitor for; a deploy re-runs the
    // seeder and must not reset it.
    $this->seed(PagesSeeder::class);

    expect($privacy->refresh()->body)->toBe('Our actual reviewed privacy policy.');
});

test('the seeder uses no factory, which production has no faker for', function () {
    // PagesSeeder runs inside the --no-dev production image via DatabaseSeeder,
    // where fakerphp/faker is absent -- a factory call here crashloops the
    // container. See .ai/rules/seeders.md.
    expect(file_get_contents(base_path('database/seeders/PagesSeeder.php')))
        ->not->toContain('factory(');
});
