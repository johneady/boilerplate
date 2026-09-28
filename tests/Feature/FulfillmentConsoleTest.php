<?php

use App\Auth\Role;
use App\Livewire\Prints\FulfillmentConsole;
use App\Models\PrintOrder;
use App\Models\User;
use App\Prints\Enums\PrintChannel;
use App\Prints\Enums\PrintOrderStatus;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->staff = User::factory()->role(Role::Manager)->create();
});

test('the console is a staff tool, not a public one', function () {
    $this->get(route('prints.fulfill'))->assertRedirect(route('login'));

    actingAs(User::factory()->create(), 'web')
        ->get(route('prints.fulfill'))
        ->assertForbidden();
});

test('a staff member sees the waiting queue', function () {
    PrintOrder::query()->forceCreate([
        'code' => 'HPL-TEST',
        'channel' => PrintChannel::InStore,
        'print_location_id' => null,
        'status' => PrintOrderStatus::Received,
        'payment_status' => 'pay_at_counter',
        'customer_name' => 'Jordan Reyes',
        'prints_total_cents' => 999,
        'list_total_cents' => 1197,
        'savings_cents' => 198,
    ]);

    actingAs($this->staff, 'web')
        ->get(route('prints.fulfill'))
        ->assertOk()
        ->assertSee('HPL-TEST')
        ->assertSee('Jordan Reyes');
});

test('sending to a printer moves the order along to handed over', function () {
    $order = PrintOrder::query()->forceCreate([
        'code' => 'HPL-PRNT',
        'channel' => PrintChannel::Remote,
        'status' => PrintOrderStatus::Received,
        'payment_status' => 'paid',
        'customer_name' => 'Priya Anand',
        'prints_total_cents' => 399,
        'list_total_cents' => 399,
        'savings_cents' => 0,
    ]);

    actingAs($this->staff, 'web');

    Livewire::test(FulfillmentConsole::class)
        ->call('sendToPrinter', $order->id);

    expect($order->refresh()->status)->toBe(PrintOrderStatus::Printing);

    // ...through to done and handed over, exactly as the counter works it.
    Livewire::test(FulfillmentConsole::class)
        ->call('markReady', $order->id);

    expect($order->refresh()->status)->toBe(PrintOrderStatus::Ready);

    Livewire::test(FulfillmentConsole::class)
        ->call('markCompleted', $order->id);

    expect($order->refresh()->status)->toBe(PrintOrderStatus::Completed);
});

test('a second tap cannot walk an order backwards', function () {
    $order = PrintOrder::query()->forceCreate([
        'code' => 'HPL-DONE',
        'channel' => PrintChannel::InStore,
        'status' => PrintOrderStatus::Ready,
        'payment_status' => 'pay_at_counter',
        'customer_name' => 'The Kim family',
        'prints_total_cents' => 399,
        'list_total_cents' => 399,
        'savings_cents' => 0,
    ]);

    actingAs($this->staff, 'web');

    // A stale tab offering "send to printer" for an order already printed.
    Livewire::test(FulfillmentConsole::class)
        ->call('sendToPrinter', $order->id);

    expect($order->refresh()->status)->toBe(PrintOrderStatus::Ready);
});
