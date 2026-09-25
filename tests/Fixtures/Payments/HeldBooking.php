<?php

namespace Tests\Fixtures\Payments;

use App\Concerns\IsPayable;
use App\Models\Payment;
use App\Payments\Contracts\Payable;
use App\Payments\Enums\CaptureMethod;
use App\Payments\Enums\Currency;
use App\Payments\Enums\PaymentAcceptance;
use App\Payments\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * A test-only payable that places a hold rather than charging, like a booking
 * deposit would.
 *
 * The boilerplate's own payable (PaymentLink) always charges at checkout, so
 * authorize/capture/void is exercised through this. It also records every
 * acceptPayment() call, and can be told to throw from it, which is how the
 * tests prove the payable is told exactly once and that a failure there rolls
 * the whole reconcile back.
 *
 * @property int $id
 * @property int $price
 * @property int $accepted_count
 * @property bool $fail_on_accept
 */
class HeldBooking extends Model implements Payable
{
    use IsPayable;

    protected $table = 'held_bookings';

    protected $guarded = [];

    public $timestamps = false;

    public static function createTable(): void
    {
        // Dropped and recreated per test rather than created once: the table
        // is test-only, so it is not in the migrations, and on the MySQL and
        // MariaDB CI matrix the CREATE's implicit commit ends the test's
        // transaction -- both making a second CREATE fatal and leaving this
        // test's rows where the rollback cannot reach them. Dropping first
        // clears those rows for whichever test runs next in the worker.
        Schema::dropIfExists('held_bookings');

        Schema::create('held_bookings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('price');
            $table->unsignedInteger('accepted_count')->default(0);
            $table->boolean('fail_on_accept')->default(false);
        });
    }

    protected function casts(): array
    {
        return ['fail_on_accept' => 'boolean', 'price' => 'integer', 'accepted_count' => 'integer'];
    }

    public function paymentDescription(): string
    {
        return 'Cabin booking';
    }

    public function paymentCurrency(): Currency
    {
        return Currency::CAD;
    }

    public function amountDue(?Money $offered = null): Money
    {
        return Money::of($this->price, Currency::CAD);
    }

    public function isTaxable(): bool
    {
        return true;
    }

    public function captureMethod(): CaptureMethod
    {
        return CaptureMethod::Manual;
    }

    public function acceptsPayments(): bool
    {
        return true;
    }

    public function acceptPayment(Payment $payment): PaymentAcceptance
    {
        static::query()->whereKey($this->id)->increment('accepted_count');

        if ($this->fail_on_accept) {
            throw new RuntimeException('The booking system is down.');
        }

        return PaymentAcceptance::Accepted;
    }

    public function payableUrl(): ?string
    {
        return null;
    }
}
