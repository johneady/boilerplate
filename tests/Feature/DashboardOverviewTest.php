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
        ->assertSee('I build web apps and get them online, from the code to the domain to the server.')
        ->assertSee('What I can build for you')
        ->assertSee('Getting it online and keeping it there')
        ->assertSee('https://www.upwork.com/freelancers/~017251040a29ffc859', escape: false);
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
