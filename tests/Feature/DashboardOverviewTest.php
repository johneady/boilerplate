<?php

use App\Filament\Pages\Dashboard;
use App\Models\User;
use Filament\Facades\Filament;

test('the panel uses the overview dashboard rather than the base one', function () {
    expect(Filament::getPanel('admin')->getPages())->toContain(Dashboard::class);
});

test('admins see the work overview on the panel landing page', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(Filament::getPanel('admin')->getUrl())
        ->assertSuccessful()
        ->assertSee('John Eady')
        ->assertSee('I build the website or app your business needs, get it live on your domain, and keep it running.')
        ->assertSee('What I can build for you')
        ->assertSee('Getting it online and keeping it there')
        ->assertSee('https://www.upwork.com/freelancers/~017251040a29ffc859', escape: false)
        ->assertSee('images/dashboard/john-eady.jpeg');
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
