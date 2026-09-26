<?php

use App\Auth\Role;
use App\Filament\Exports\PaymentExporter;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\Payment;
use App\Models\PaymentLink;
use App\Models\Refund;
use App\Models\Subscription;
use App\Models\TaxRate;
use App\Models\User;
use App\Payments\Actions\RecordManualPayment;
use App\Payments\Actions\RefundPayment;
use App\Payments\Enums\Currency;
use App\Payments\Enums\ManualPaymentMethod;
use App\Payments\Enums\RefundStatus;
use App\Payments\Enums\SubscriptionStatus;
use App\Payments\Money;
use App\Payments\ReceiptPdf;
use App\Settings\SettingKey;
use App\Settings\Settings;
use Carbon\CarbonImmutable;
use Filament\Actions\Exports\Models\Export;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Support\Payments;

beforeEach(function () {
    Payments::enable();
    Storage::fake('local');
});

/**
 * The CSV a finished export offers for download, as rows of cells.
 *
 * Filament writes the header row and each chunk of records to separate files
 * in the export's directory and joins them when the file is downloaded.
 *
 * @return list<list<string>>
 */
function exportedRows(?Export $export = null): array
{
    $export ??= Export::query()->latest('id')->firstOrFail();
    $disk = Storage::disk($export->file_disk);
    $directory = $export->getFileDirectory();

    $files = collect($disk->files($directory))
        ->filter(fn (string $file): bool => str_ends_with($file, '.csv'))
        ->sortBy(fn (string $file): string => basename($file) === 'headers.csv' ? '' : basename($file));

    // Filament opens the header row with a byte-order mark, so Excel reads
    // the file as UTF-8.
    $csv = ltrim($files->map(fn (string $file): string => (string) $disk->get($file))->implode(''), "\u{FEFF}");

    return array_values(array_map(
        fn (string $line): array => str_getcsv($line, escape: '\\'),
        array_filter(preg_split('/\r?\n/', $csv) ?: [], fn (string $line): bool => $line !== ''),
    ));
}

/**
 * The value in one named column of each data row.
 *
 * @param  list<list<string>>  $rows
 * @return list<string>
 */
function exportedColumn(array $rows, string $header): array
{
    $index = array_search($header, $rows[0], true);

    expect($index)->not->toBeFalse("The export has no [{$header}] column.");

    return array_map(fn (array $row): string => $row[$index], array_slice($rows, 1));
}

test('a bookkeeper exports payments with a column per tax', function () {
    TaxRate::factory()->rate('GST', '5')->create();
    TaxRate::factory()->rate('PST', '7')->create();
    Payments::payWithDemo(PaymentLink::factory()->costing(10000)->taxable()->create(['title' => 'Logo design']));

    $this->actingAs(User::factory()->role(Role::Bookkeeper)->create());

    Livewire::test(ListPayments::class)->callTableAction('export');

    $rows = exportedRows();

    expect(exportedColumn($rows, 'Receipt number'))->toBe(['R-000001'])
        ->and(exportedColumn($rows, 'Description'))->toBe(['Logo design'])
        ->and(exportedColumn($rows, 'Subtotal'))->toBe(['100.00'])
        ->and(exportedColumn($rows, 'GST'))->toBe(['5.00'])
        ->and(exportedColumn($rows, 'PST'))->toBe(['7.00'])
        ->and(exportedColumn($rows, 'Total'))->toBe(['112.00'])
        ->and(exportedColumn($rows, 'Net'))->toBe(['112.00']);
});

test('the payments export follows the table\'s filters', function () {
    $this->travelTo(now()->subDays(40));
    $old = Payments::payWithDemo(PaymentLink::factory()->create(['title' => 'Old sale']));
    $this->travelBack();
    $recent = Payments::payWithDemo(PaymentLink::factory()->create(['title' => 'Recent sale']));

    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ListPayments::class)
        ->filterTable('paid_between', ['paid_from' => now()->subDays(7)->toDateString()])
        ->assertCanSeeTableRecords([$recent])
        ->assertCanNotSeeTableRecords([$old])
        ->callTableAction('export');

    expect(exportedColumn(exportedRows(), 'Description'))->toBe(['Recent sale']);
});

test('the refunds export lists refunds in the chosen dates, not payments', function () {
    $payment = Payments::payWithDemo(PaymentLink::factory()->costing(5000)->create(['title' => 'Workshop seat']));
    app(RefundPayment::class)->handle($payment, Money::of(1500, $payment->currency), 'refund-key', 'Could not attend', User::factory()->admin()->create());

    // Paid but never refunded: must not appear.
    Payments::payWithDemo(PaymentLink::factory()->create());

    $this->actingAs(User::factory()->role(Role::Bookkeeper)->create());

    Livewire::test(ListPayments::class)
        ->callAction('exportRefunds', ['refunded_from' => now()->subDay()->toDateString()]);

    $rows = exportedRows();

    expect(exportedColumn($rows, 'Receipt number'))->toBe(['R-000001'])
        ->and(exportedColumn($rows, 'Amount'))->toBe(['15.00'])
        ->and(exportedColumn($rows, 'Reason'))->toBe(['Could not attend']);
});

test('refunds outside the chosen dates are left out', function () {
    $payment = Payments::payWithDemo(PaymentLink::factory()->costing(5000)->create());
    app(RefundPayment::class)->handle($payment, Money::of(1500, $payment->currency), 'refund-key', null, User::factory()->admin()->create());

    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ListPayments::class)
        ->callAction('exportRefunds', ['refunded_until' => now()->subDays(2)->toDateString()]);

    expect(exportedRows())->toHaveCount(1);
});

test('a manager exports the user list', function () {
    User::factory()->create(['name' => 'Priya Customer', 'email' => 'priya@example.test']);

    $this->actingAs(User::factory()->role(Role::Manager)->create());

    Livewire::test(ManageUsers::class)->callTableAction('export');

    expect(exportedColumn(exportedRows(), 'Email'))->toContain('priya@example.test');
});

test('the users export shows each user\'s current subscription', function () {
    $user = User::factory()->create(['email' => 'sub@example.test']);
    $live = Subscription::factory()->for($user)->create(['status' => SubscriptionStatus::Active]);
    // Newer, but over: the live one is still the current one.
    Subscription::factory()->for($user)->create(['status' => SubscriptionStatus::Canceled]);

    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ManageUsers::class)->callTableAction('export');

    $rows = exportedRows();
    $row = array_search('sub@example.test', exportedColumn($rows, 'Email'), true);

    expect(exportedColumn($rows, 'Subscription')[$row])->toBe($live->plan->name.' (Active)');
});

test('the users export reads subscriptions in a fixed number of queries, however many users', function (int $users) {
    Subscription::factory()->count($users)->create();

    $this->actingAs(User::factory()->admin()->create());

    $queries = 0;
    DB::listen(function ($query) use (&$queries): void {
        if (str_contains($query->sql, 'from "subscriptions"') || str_contains($query->sql, 'from `subscriptions`')) {
            $queries++;
        }
    });

    Livewire::test(ManageUsers::class)->callTableAction('export');

    // One query for every chunk's subscriptions, not one per user.
    expect($queries)->toBe(1);
})->with([3, 30]);

test('text a customer typed cannot run as a spreadsheet formula', function () {
    $payment = Payments::payWithDemo(PaymentLink::factory()->create());
    Payment::query()->whereKey($payment->id)->update(['customer_name' => '=HYPERLINK("http://evil.test")']);

    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ListPayments::class)->callTableAction('export');

    expect(exportedColumn(exportedRows(), 'Customer'))->toBe(["'=HYPERLINK(\"http://evil.test\")"]);
});

test('the finished export is announced in the panel', function () {
    // On a real queue the download link arrives as a database notification,
    // which needs the panel's notification bell; the sync queue these tests
    // run on sends it as a flash instead, so both halves are checked here.
    $export = new Export(['total_rows' => 1, 'successful_rows' => 1]);

    expect(Filament::getPanel('admin')->hasDatabaseNotifications())->toBeTrue()
        ->and(PaymentExporter::getCompletedNotificationBody($export))->toBe('Your export is ready: 1 row.');
});

test('only paid payments are exported, so tax columns sum to tax collected', function () {
    TaxRate::factory()->rate('GST', '5')->create();
    Payments::payWithDemo(PaymentLink::factory()->costing(10000)->taxable()->create(['title' => 'Paid']));
    Payments::payWithDemo(PaymentLink::factory()->costing(10000)->taxable()->create(['title' => 'Declined']), 'decline');

    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ListPayments::class)->callTableAction('export');

    expect(exportedColumn(exportedRows(), 'Description'))->toBe(['Paid']);
});

test('a refund attempt that failed is not exported beside the one that went through', function () {
    $payment = Payments::payWithDemo(PaymentLink::factory()->costing(5000)->create());
    app(RefundPayment::class)->handle($payment, Money::of(1500, $payment->currency), 'refund-key', null, User::factory()->admin()->create());
    Refund::factory()->for($payment)->create(['status' => RefundStatus::Failed, 'amount' => 1500]);

    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ListPayments::class)->callAction('exportRefunds');

    expect(exportedColumn(exportedRows(), 'Amount'))->toBe(['15.00']);
});

test('a manual payment keeps the day it was received, west of UTC', function () {
    app(Settings::class)->set(SettingKey::Timezone, 'America/Vancouver');

    $payment = app(RecordManualPayment::class)->handle(
        payable: PaymentLink::factory()->create(),
        subtotal: Money::of(5000, Currency::CAD),
        method: ManualPaymentMethod::Cheque,
        reference: null,
        receivedOn: CarbonImmutable::parse('2026-09-01'),
        customerName: 'Sam Customer',
        customerEmail: 'sam@example.test',
        idempotencyKey: 'manual-key',
        recorder: User::factory()->admin()->create(),
    );

    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ListPayments::class)->callTableAction('export');

    expect(exportedColumn(exportedRows(), 'Paid on'))->toBe(['2026-09-01'])
        ->and(app(ReceiptPdf::class)->html($payment->refresh()))
        ->toContain('Paid '.app(Settings::class)->formatCalendarDate(CarbonImmutable::parse('2026-09-01')));
});

test('exports and their files are deleted after a week', function () {
    $user = User::factory()->create();
    $old = Export::query()->forceCreate(['file_disk' => 'local', 'exporter' => 'x', 'total_rows' => 1, 'user_id' => $user->id, 'created_at' => now()->subDays(8)]);
    $recent = Export::query()->forceCreate(['file_disk' => 'local', 'exporter' => 'x', 'total_rows' => 1, 'user_id' => $user->id]);

    Storage::disk('local')->put($old->getFileDirectory().'/headers.csv', 'a');
    Storage::disk('local')->put($recent->getFileDirectory().'/headers.csv', 'a');
    // A directory whose row went with its user.
    Storage::disk('local')->put('filament_exports/999/headers.csv', 'a');

    DB::table('notifications')->insert([
        'id' => (string) Str::uuid(),
        'type' => 'Filament\\Notifications\\DatabaseNotification',
        'notifiable_type' => User::class,
        'notifiable_id' => $user->id,
        'data' => json_encode(['actions' => [['url' => '/filament/exports/'.$old->id.'/download?format=csv']]]),
        'created_at' => now()->subDays(8),
        'updated_at' => now()->subDays(8),
    ]);

    $this->artisan('app:prune-exports')->assertSuccessful();

    expect(DB::table('notifications')->count())->toBe(0);

    expect(Export::query()->whereKey($old->id)->exists())->toBeFalse()
        ->and(Storage::disk('local')->exists($old->getFileDirectory()))->toBeFalse()
        ->and(Storage::disk('local')->exists($recent->getFileDirectory().'/headers.csv'))->toBeTrue()
        ->and(Storage::disk('local')->exists('filament_exports/999'))->toBeFalse();
});
