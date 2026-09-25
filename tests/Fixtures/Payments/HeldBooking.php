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
 * Its table is a test-only migration (tests/Fixtures/migrations), created with
 * the rest of the schema rather than by the tests that use it.
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
