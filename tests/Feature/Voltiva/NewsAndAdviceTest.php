<?php

use App\Models\Article;
use App\Models\Page;
use App\Models\Vehicle;
use App\Voltiva\ArticleTopic;

test('the listing filters by topic', function () {
    Article::factory()->published()->create(['title' => 'Charging at home', 'topic' => ArticleTopic::Charging]);
    Article::factory()->published()->create(['title' => 'Battery life', 'topic' => ArticleTopic::Batteries]);

    $this->get(route('news.index', ['topic' => 'charging']))
        ->assertSee('Charging at home')
        ->assertDontSee('Battery life');
});

test('a malformed topic filter shows every article', function () {
    Article::factory()->published()->create(['title' => 'Charging at home']);

    $this->get('/news?topic[]=charging')->assertSee('Charging at home');
});

test('drafts and articles scheduled for later are hidden', function () {
    $draft = Article::factory()->create(['title' => 'Draft article']);
    $scheduled = Article::factory()->published()->create(['title' => 'Scheduled article', 'published_at' => now()->addDay()]);

    $this->get(route('news.index'))
        ->assertDontSee('Draft article')
        ->assertDontSee('Scheduled article');
    $this->get(route('news.show', $draft))->assertNotFound();
    $this->get(route('news.show', $scheduled))->assertNotFound();
});

test('an article links back to its topic and shows its car', function () {
    $vehicle = Vehicle::factory()->published()->create(['name' => 'Voltiva Terra']);
    $article = Article::factory()->published()->create(['topic' => ArticleTopic::Charging, 'vehicle_id' => $vehicle->id]);

    $this->get(route('news.show', $article))
        ->assertSee(route('pages.show', 'charging'))
        ->assertSee('Explore Voltiva Terra');
});

test('an article body is rendered as escaped markdown', function () {
    $article = Article::factory()->published()->create(['body' => "## Heading\n\n<script>alert(1)</script>"]);

    $this->get(route('news.show', $article))
        ->assertSee('<h2>Heading</h2>', false)
        ->assertDontSee('<script>alert(1)</script>', false);
});

test('a topic page lists the advice written on its topic', function () {
    Page::factory()->published()->create(['slug' => 'charging', 'title' => 'Charging']);
    Article::factory()->published()->create(['title' => 'Charging at home', 'topic' => ArticleTopic::Charging]);
    Article::factory()->published()->create(['title' => 'Battery life', 'topic' => ArticleTopic::Batteries]);

    $this->get('/charging')
        ->assertSee('Charging at home')
        ->assertDontSee('Battery life');
});

test('the sitemap lists published cars and articles, not drafts', function () {
    $car = Vehicle::factory()->published()->create();
    $draftCar = Vehicle::factory()->create();
    $article = Article::factory()->published()->create();

    $this->get(route('sitemap'))
        ->assertSee(route('cars.show', $car))
        ->assertSee(route('news.show', $article))
        ->assertDontSee(route('cars.show', $draftCar));
});

test('the sitemap lists a car class only while it has a published car', function () {
    Vehicle::factory()->published()->create();
    Vehicle::factory()->l7e()->create();

    $this->get(route('sitemap'))
        ->assertSee('<loc>'.route('cars.category', 'l6e').'</loc>', false)
        ->assertDontSee('<loc>'.route('cars.category', 'l7e').'</loc>', false);
});
