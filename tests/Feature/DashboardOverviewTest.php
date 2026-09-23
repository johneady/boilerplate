<?php

use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\BusinessOverview;
use App\Models\Enquiry;
use App\Models\User;
use App\Voltiva\EnquiryStatus;
use Filament\Facades\Filament;
use Livewire\Livewire;

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
        ->assertSee('The widgets above are generic examples')
        ->assertSee('In the finished product they are replaced with widgets built around your real business data.')
        ->assertSee('Show introduction')
        ->assertSee("x-on:click=\"\$dispatch('open-modal', { id: 'work-overview' })\"", escape: false);
});

test('the landing page embeds the business overview widget', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(Filament::getPanel('admin')->getUrl())
        ->assertSuccessful()
        ->assertSee('Widgets\BusinessOverview', escape: false);
});

test('the business overview widget reports the live enquiry pipeline', function () {
    Enquiry::factory()->count(2)->create(['finance_interest' => true]);
    Enquiry::factory()->create(['status' => EnquiryStatus::Won]);

    Livewire::test(BusinessOverview::class)
        ->assertSee('Voltiva at a glance')
        ->assertSee('Enquiries this week')
        ->assertSee('2 waiting for first contact')
        ->assertSee('1 sold so far')
        ->assertSee('67%');
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
