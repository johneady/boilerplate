<?php

use App\Auth\Role;
use App\Filament\Resources\TripInquiries\Pages\ListTripInquiries;
use App\Filament\Resources\TripInquiries\Pages\ViewTripInquiry;
use App\Models\TourDeparture;
use App\Models\TripInquiry;
use App\Models\User;
use App\Travel\InquiryStatus;
use Livewire\Livewire;

function staff(Role $role): User
{
    $user = User::factory()->create();
    $user->role = $role;
    $user->save();

    return $user;
}

test('a manager can confirm a request from its page, holding the seats', function () {
    $departure = TourDeparture::factory()->seatsLeft(5)->create();
    $inquiry = TripInquiry::factory()->forDeparture($departure)->create(['adults' => 2]);

    Livewire::actingAs(staff(Role::Manager))
        ->test(ViewTripInquiry::class, ['record' => $inquiry->getRouteKey()])
        ->callAction('confirm')
        ->assertNotified();

    expect($inquiry->fresh()->status)->toBe(InquiryStatus::Confirmed)
        ->and($departure->fresh()->seatsLeft())->toBe(3);
});

test('confirming more travellers than seats left is refused with a message', function () {
    $departure = TourDeparture::factory()->seatsLeft(1)->create();
    $inquiry = TripInquiry::factory()->forDeparture($departure)->create(['adults' => 2]);

    Livewire::actingAs(staff(Role::Manager))
        ->test(ViewTripInquiry::class, ['record' => $inquiry->getRouteKey()])
        ->callAction('confirm')
        ->assertNotified('Only 1 seats are left on this departure; the request is for 2.');

    expect($inquiry->fresh()->status)->toBe(InquiryStatus::New);
});

test('a bookkeeper can read requests but not confirm them', function () {
    $inquiry = TripInquiry::factory()->create();
    $bookkeeper = staff(Role::Bookkeeper);

    $this->actingAs($bookkeeper)->get(ViewTripInquiry::getUrl(['record' => $inquiry]))->assertOk();

    Livewire::actingAs($bookkeeper)
        ->test(ViewTripInquiry::class, ['record' => $inquiry->getRouteKey()])
        ->assertActionHidden('confirm');
});

test('a plain user cannot reach the inbox', function () {
    $this->actingAs(User::factory()->create())->get(ListTripInquiries::getUrl())->assertForbidden();
});

test('a traveller\'s notes are shown escaped', function () {
    $inquiry = TripInquiry::factory()->create(['message' => '<script>alert(1)</script>']);

    $this->actingAs(staff(Role::Manager))
        ->get(ViewTripInquiry::getUrl(['record' => $inquiry]))
        ->assertOk()
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
});
