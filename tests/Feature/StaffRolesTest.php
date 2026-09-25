<?php

use App\Auth\Role;
use App\Filament\Resources\Payments\Pages\ViewPayment;
use App\Filament\Resources\TaxRates\Pages\ManageTaxRates;
use App\Filament\Resources\TaxRates\TaxRateResource;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Filament\Resources\Users\UserResource;
use App\Models\Page;
use App\Models\PaymentLink;
use App\Models\TaxRate;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Support\Payments;

beforeEach(function () {
    // On, so the payment screens answer on permission rather than being
    // closed to everyone by the module switch.
    Payments::enable();
});

test('staff roles land on the admin panel after logging in', function (Role $role) {
    $user = User::factory()->role($role)->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(Filament::getPanel('admin')->getUrl());
})->with([Role::Editor, Role::Bookkeeper, Role::Manager]);

test('each staff role reaches only the screens its work needs', function (Role $role, string $path, bool $allowed) {
    $response = $this->actingAs(User::factory()->role($role)->create())->get($path);

    $allowed ? $response->assertSuccessful() : $response->assertForbidden();
})->with([
    'editor: pages' => [Role::Editor, '/admin/pages', true],
    'editor: media' => [Role::Editor, '/admin/media', true],
    'editor: contact messages' => [Role::Editor, '/admin/contact-submissions', true],
    'editor: payments' => [Role::Editor, '/admin/payments', false],
    'editor: users' => [Role::Editor, '/admin/users', false],
    'editor: settings' => [Role::Editor, '/admin/settings', false],

    'bookkeeper: payments' => [Role::Bookkeeper, '/admin/payments', true],
    'bookkeeper: subscriptions' => [Role::Bookkeeper, '/admin/subscriptions', true],
    'bookkeeper: disputes' => [Role::Bookkeeper, '/admin/disputes', true],
    'bookkeeper: tax rates' => [Role::Bookkeeper, '/admin/tax-rates', true],
    'bookkeeper: payment links' => [Role::Bookkeeper, '/admin/payment-links', false],
    'bookkeeper: pages' => [Role::Bookkeeper, '/admin/pages', false],
    'bookkeeper: users' => [Role::Bookkeeper, '/admin/users', false],

    'manager: payments' => [Role::Manager, '/admin/payments', true],
    'manager: payment links' => [Role::Manager, '/admin/payment-links', true],
    'manager: pages' => [Role::Manager, '/admin/pages', true],
    'manager: users' => [Role::Manager, '/admin/users', true],
    'manager: settings' => [Role::Manager, '/admin/settings', false],
    'manager: plans' => [Role::Manager, '/admin/plans', false],
    'manager: webhook events' => [Role::Manager, '/admin/webhook-events', false],
    'manager: audit log' => [Role::Manager, '/admin/audit-logs', false],
    'manager: logs' => [Role::Manager, '/admin/logs', false],
]);

test('a bookkeeper reads tax rates but cannot change or reorder them', function () {
    $rate = TaxRate::factory()->create();

    $this->actingAs(User::factory()->role(Role::Bookkeeper)->create());

    Livewire::test(ManageTaxRates::class)
        ->assertCanSeeTableRecords([$rate])
        ->assertActionHidden(CreateAction::class)
        ->assertTableActionHidden(EditAction::class, $rate);

    // Filament allows an ability whose policy method is missing, so reorder
    // was open to anyone who could view the table until BasePolicy answered it.
    expect(TaxRateResource::canReorder())->toBeFalse();
});

test('a manager sees the user list but cannot edit anyone or change roles', function () {
    $customer = User::factory()->create();

    $this->actingAs(User::factory()->role(Role::Manager)->create());

    Livewire::test(ManageUsers::class)
        ->assertCanSeeTableRecords([$customer])
        ->assertActionHidden(CreateAction::class)
        ->assertTableActionHidden(EditAction::class, $customer);

    // DeleteBulkAction checks only the class-level deleteAny, never each
    // selected record, so this is the whole guard on bulk-deleting users.
    expect(UserResource::canDeleteAny())->toBeFalse();
});

test('bulk actions borrow the permission of the ability they batch', function () {
    $editor = User::factory()->role(Role::Editor)->create();

    // Pages: the editor may delete one, so may delete several. Users: may
    // not delete one, so may not delete several either.
    expect($editor->can('deleteAny', Page::class))->toBeTrue()
        ->and($editor->can('deleteAny', User::class))->toBeFalse()
        ->and($editor->can('reorder', Page::class))->toBeTrue()
        ->and($editor->can('attach', Page::class))->toBeFalse();
});

test('refunding is offered to a manager and withheld from a bookkeeper', function (Role $role, bool $offered) {
    $payment = Payments::payWithDemo(PaymentLink::factory()->costing(5000)->create());

    $this->actingAs(User::factory()->role($role)->create());

    $page = Livewire::test(ViewPayment::class, ['record' => $payment->getRouteKey()]);

    $offered ? $page->assertActionVisible('refund') : $page->assertActionHidden('refund');
})->with([
    'manager' => [Role::Manager, true],
    'bookkeeper' => [Role::Bookkeeper, false],
]);

test('the public header links staff to the admin panel', function () {
    $this->actingAs(User::factory()->role(Role::Editor)->create())
        ->get(route('contact'))
        ->assertSee(Filament::getPanel('admin')->getUrl());
});
