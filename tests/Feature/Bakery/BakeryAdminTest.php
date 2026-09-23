<?php

use App\Bakery\InquiryStatus;
use App\Filament\Resources\MenuItems\Pages\ManageMenuItems;
use App\Filament\Resources\OrderInquiries\OrderInquiryResource;
use App\Filament\Resources\OrderInquiries\Pages\ListOrderInquiries;
use App\Filament\Resources\OrderInquiries\Pages\ViewOrderInquiry;
use App\Filament\Widgets\BakingSchedule;
use App\Filament\Widgets\BusinessOverview;
use App\Models\MenuItem;
use App\Models\OrderInquiry;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create());
});

test('an administrator adds a menu item with the price entered as a decimal', function () {
    Livewire::test(ManageMenuItems::class)
        ->callAction('create', [
            'name' => 'Lemon Drizzle Loaf',
            'slug' => 'lemon-drizzle-loaf',
            'category' => 'cakes',
            'description' => 'Zesty and sticky.',
            'price_cents' => '18.50',
            'price_unit' => 'per loaf',
            'notice_days' => 3,
            'is_available' => true,
        ])
        ->assertHasNoActionErrors();

    expect(MenuItem::sole())
        ->price_cents->toBe(1850)
        ->notice_days->toBe(3);
});

test('a menu item can be switched off from the table', function () {
    $item = MenuItem::factory()->create();

    Livewire::test(ManageMenuItems::class)
        ->call('updateTableColumnState', 'is_available', (string) $item->getKey(), false);

    expect($item->refresh()->is_available)->toBeFalse();
});

test('the needs-a-reply tab lists only new inquiries', function () {
    $new = OrderInquiry::factory()->create();
    $confirmed = OrderInquiry::factory()->status(InquiryStatus::Confirmed)->create();

    Livewire::test(ListOrderInquiries::class)
        ->set('activeTab', 'new')
        ->assertCanSeeTableRecords([$new])
        ->assertCanNotSeeTableRecords([$confirmed]);
});

test('the upcoming tab lists quoted and confirmed orders from today, soonest first', function () {
    $later = OrderInquiry::factory()->status(InquiryStatus::Confirmed)->create(['needed_on' => now()->addDays(9)->toDateString()]);
    $sooner = OrderInquiry::factory()->status(InquiryStatus::Quoted)->create(['needed_on' => now()->addDays(2)->toDateString()]);
    $past = OrderInquiry::factory()->status(InquiryStatus::Confirmed)->create(['needed_on' => now()->subDays(2)->toDateString()]);

    Livewire::test(ListOrderInquiries::class)
        ->set('activeTab', 'upcoming')
        ->assertCanSeeTableRecords([$sooner, $later], inOrder: true)
        ->assertCanNotSeeTableRecords([$past]);
});

test('the baker records a status, a quote and private notes', function () {
    $inquiry = OrderInquiry::factory()->create();

    Livewire::test(ViewOrderInquiry::class, ['record' => $inquiry->getRouteKey()])
        ->callAction('update', [
            'status' => InquiryStatus::Quoted->value,
            'quoted_total_cents' => '98.00',
            'baker_notes' => 'Blush and gold boxes.',
        ])
        ->assertHasNoActionErrors();

    expect($inquiry->refresh())
        ->status->toBe(InquiryStatus::Quoted)
        ->quoted_total_cents->toBe(9800)
        ->baker_notes->toBe('Blush and gold boxes.');
});

test('the inquiry page shows the lines the customer asked for', function () {
    $inquiry = OrderInquiry::factory()->create();

    $this->get(OrderInquiryResource::getUrl('view', ['record' => $inquiry]))
        ->assertSuccessful()
        ->assertSee($inquiry->reference)
        ->assertSee('Cinnamon Rolls');
});

test('a customer account cannot reach the order book', function () {
    $this->actingAs(User::factory()->create())
        ->get(OrderInquiryResource::getUrl('index'))
        ->assertForbidden();
});

test('the overview counts inquiries waiting for a reply and orders due this week', function () {
    OrderInquiry::factory()->count(2)->create();
    OrderInquiry::factory()->status(InquiryStatus::Confirmed)->create(['needed_on' => now()->addDays(3)->toDateString()]);
    OrderInquiry::factory()->status(InquiryStatus::Confirmed)->create(['needed_on' => now()->addDays(20)->toDateString()]);

    Livewire::test(BusinessOverview::class)
        ->assertSeeInOrder(['Waiting for a reply', '2'])
        ->assertSeeInOrder(['Due in the next 7 days', '1']);
});

test('the baking schedule lists the next two weeks of accepted orders', function () {
    $soon = OrderInquiry::factory()->status(InquiryStatus::Confirmed)->create(['needed_on' => now()->addDays(5)->toDateString()]);
    $tooFar = OrderInquiry::factory()->status(InquiryStatus::Confirmed)->create(['needed_on' => now()->addDays(30)->toDateString()]);
    $unanswered = OrderInquiry::factory()->create(['needed_on' => now()->addDays(5)->toDateString()]);

    Livewire::test(BakingSchedule::class)
        ->assertCanSeeTableRecords([$soon])
        ->assertCanNotSeeTableRecords([$tooFar, $unanswered]);
});
