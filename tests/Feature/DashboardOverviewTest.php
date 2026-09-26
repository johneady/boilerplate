<?php

use App\Filament\Pages\Dashboard;
use App\Models\User;
use Filament\Facades\Filament;
use Tests\Support\Payments;

test('the panel uses the overview dashboard rather than the base one', function () {
    expect(Filament::getPanel('admin')->getPages())->toContain(Dashboard::class);
});

test('admins see the work overview on the panel landing page', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(Filament::getPanel('admin')->getUrl())
        ->assertSuccessful()
        ->assertSee('John Eady')
        ->assertSee('I build your website, get it online, and keep it running.')
        ->assertSee('What I can build for you')
        ->assertSee('Getting it online and keeping it there')
        ->assertSee('https://www.upwork.com/freelancers/~017251040a29ffc859', escape: false)
        ->assertSee('images/dashboard/john-eady.jpeg');
});

test('the work overview opens itself once per visitor', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(Filament::getPanel('admin')->getUrl())
        ->assertSuccessful()
        ->assertSee("window.localStorage.getItem('work-overview-introduced')", escape: false)
        ->assertSee("setTimeout(() => \$dispatch('open-modal', { id: 'work-overview' }), 10000)", escape: false);
});

test('a control on the landing page reopens the work overview', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(Filament::getPanel('admin')->getUrl())
        ->assertSuccessful()
        ->assertSee('The figures above are demo data')
        ->assertSee('In the finished product they come from your real sales, customers and messages.')
        ->assertSee('Show introduction')
        ->assertSee("x-on:click=\"\$dispatch('open-modal', { id: 'work-overview' })\"", escape: false);
});

test('the landing page embeds the business overview widget', function () {
    Payments::enable();

    $this->actingAs(User::factory()->admin()->create())
        ->get(Filament::getPanel('admin')->getUrl())
        ->assertSuccessful()
        ->assertSee('Widgets\BusinessOverview', escape: false);
});

test('a production instance never carries the work overview', function () {
    app()->detectEnvironment(fn () => 'production');

    try {
        $response = $this->actingAs(User::factory()->admin()->create())
            ->get(Filament::getPanel('admin')->getUrl());
    } finally {
        app()->detectEnvironment(fn () => 'testing');
    }

    $response->assertSuccessful()
        ->assertDontSee('John Eady')
        ->assertDontSee('The figures above are demo data');
});

test('the hero portrait is present on disk', function () {
    expect(public_path('images/dashboard/john-eady.jpeg'))->toBeFile();
});

test('non-admins may not reach the dashboard', function () {
    $this->actingAs(User::factory()->create())
        ->get(Filament::getPanel('admin')->getUrl())
        ->assertForbidden();
});

test('guests are sent to the application login page', function () {
    $this->get(Filament::getPanel('admin')->getUrl())
        ->assertRedirect(route('login'));
});
