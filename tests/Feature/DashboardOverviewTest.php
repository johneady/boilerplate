<?php

use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\BusinessOverview;
use App\Filament\Widgets\RecentOrders;
use App\Filament\Widgets\RevenueTrendChart;
use App\Filament\Widgets\TopProducts;
use App\Filament\Widgets\TrafficSourcesChart;
use App\Models\User;
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
        ->assertSee('You run your business. I build the website, get it online, and keep it working.')
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
        ->assertSee('The widgets below are generic examples')
        ->assertSee('In the finished product they are replaced with widgets built around your real business data.')
        ->assertSee('Show introduction')
        ->assertSee("x-on:click=\"\$dispatch('open-modal', { id: 'work-overview' })\"", escape: false);
});

test('the landing page embeds the business overview widgets', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(Filament::getPanel('admin')->getUrl())
        ->assertSuccessful()
        ->assertSee('Widgets\BusinessOverview', escape: false)
        ->assertSee('Widgets\RevenueTrendChart', escape: false)
        ->assertSee('Widgets\TrafficSourcesChart', escape: false)
        ->assertSee('Widgets\RecentOrders', escape: false)
        ->assertSee('Widgets\TopProducts', escape: false);
});

test('the business overview widgets render their sample data', function () {
    Livewire::test(BusinessOverview::class)
        ->assertSee('This month at a glance')
        ->assertSee('Revenue this month')
        ->assertSee('$48,650');

    Livewire::test(RevenueTrendChart::class)
        ->assertSee('Revenue vs expenses')
        ->assertSee('Rolling 12 months');

    Livewire::test(TrafficSourcesChart::class)
        ->assertSee('Traffic sources')
        ->assertSee('Visits over the last 30 days');

    Livewire::test(RecentOrders::class)
        ->assertSee('Recent orders')
        ->assertSee('Amelia Hartley')
        ->assertSee('$1,140.00');

    Livewire::test(TopProducts::class)
        ->assertSee('Top products')
        ->assertSee('Website care plan (annual)')
        ->assertSee('$16,640');
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
