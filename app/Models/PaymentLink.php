<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\IsPayable;
use App\Payments\Contracts\Payable;
use App\Payments\Enums\CaptureMethod;
use App\Payments\Enums\Currency;
use App\Payments\Enums\PaymentAcceptance;
use App\Payments\Enums\PaymentLinkAmountType;
use App\Payments\Enums\PaymentLinkUsage;
use App\Payments\Exceptions\InvalidPaymentAmount;
use App\Payments\Money;
use Carbon\CarbonImmutable;
use Database\Factories\PaymentLinkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A shareable /pay/{token} page for a fixed or customer-entered amount.
 *
 * The boilerplate's own Payable, and the worked example of the contract: an
 * invoice or deposit (single-use) or a fixed-price service or donation page
 * (reusable) that needs nothing built around it to take money.
 *
 * @property int $id
 * @property string $token
 * @property string $title
 * @property string|null $description
 * @property PaymentLinkAmountType $amount_type
 * @property int|null $amount
 * @property int|null $min_amount
 * @property int|null $max_amount
 * @property Currency $currency
 * @property bool $taxable
 * @property PaymentLinkUsage $usage
 * @property CarbonImmutable|null $expires_at
 * @property bool $is_active
 * @property int|null $settled_payment_id
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['title', 'description', 'amount_type', 'amount', 'min_amount', 'max_amount', 'currency', 'taxable', 'usage', 'expires_at', 'is_active'])]
class PaymentLink extends Model implements Payable
{
    /** @use HasFactory<PaymentLinkFactory> */
    use Auditable, HasFactory, IsPayable;

    protected static function booted(): void
    {
        static::creating(function (PaymentLink $link): void {
            if (blank($link->token)) {
                // 32 characters of [a-zA-Z0-9]: about 190 bits, so a link
                // cannot be found by guessing.
                $link->token = Str::random(32);
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_type' => PaymentLinkAmountType::class,
            'usage' => PaymentLinkUsage::class,
            'currency' => Currency::class,
            'amount' => 'integer',
            'min_amount' => 'integer',
            'max_amount' => 'integer',
            'taxable' => 'boolean',
            'is_active' => 'boolean',
            'expires_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return list<string>
     */
    protected function auditExclude(): array
    {
        return ['token', 'updated_at'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function paymentDescription(): string
    {
        return $this->title;
    }

    public function paymentCurrency(): Currency
    {
        return $this->currency;
    }

    public function amountDue(?Money $offered = null): Money
    {
        if ($this->amount_type === PaymentLinkAmountType::Fixed) {
            return Money::of((int) $this->amount, $this->currency);
        }

        if ($offered === null || ! $offered->isPositive()) {
            throw new InvalidPaymentAmount(__('Enter the amount you would like to pay.'));
        }

        if ($offered->currency !== $this->currency) {
            throw new InvalidPaymentAmount(__('This payment must be made in :currency.', ['currency' => $this->currency->value]));
        }

        if ($this->min_amount !== null && $offered->amount < $this->min_amount) {
            throw new InvalidPaymentAmount(__('The minimum amount is :amount.', ['amount' => $this->minimum()?->format()]));
        }

        if ($this->max_amount !== null && $offered->amount > $this->max_amount) {
            throw new InvalidPaymentAmount(__('The maximum amount is :amount.', ['amount' => $this->maximum()?->format()]));
        }

        return $offered;
    }

    public function isTaxable(): bool
    {
        return $this->taxable;
    }

    /**
     * Payment links always take the money at checkout. Holds are for payables
     * whose fulfilment comes later, such as a booking.
     */
    public function captureMethod(): CaptureMethod
    {
        return CaptureMethod::Automatic;
    }

    public function acceptsPayments(): bool
    {
        return $this->is_active
            && ! $this->isExpired()
            && ! ($this->usage === PaymentLinkUsage::SingleUse && $this->settled_payment_id !== null);
    }

    /**
     * Claim a single-use link for the payment that settled it.
     *
     * Two customers can open the same invoice link and both complete checkout.
     * Each payment is reconciled under its OWN row lock, so without a lock on
     * the link both would see it unsettled and both would be accepted. Taking
     * the link's row lock and reading settled_payment_id through it serialises
     * them: the second sees the first's claim and is reported as a duplicate,
     * which the caller refunds.
     *
     * A locking read rather than the loaded attribute, because the loaded
     * value predates the lock and a snapshot read under REPEATABLE READ can
     * predate the other transaction's commit.
     */
    public function acceptPayment(Payment $payment): PaymentAcceptance
    {
        if ($this->usage === PaymentLinkUsage::Reusable) {
            return PaymentAcceptance::Accepted;
        }

        $settledBy = static::query()->whereKey($this->getKey())->lockForUpdate()->value('settled_payment_id');

        if ($settledBy === null) {
            static::query()->whereKey($this->getKey())->update(['settled_payment_id' => $payment->id]);
            $this->settled_payment_id = $payment->id;

            return PaymentAcceptance::Accepted;
        }

        return (int) $settledBy === $payment->id ? PaymentAcceptance::Accepted : PaymentAcceptance::Duplicate;
    }

    public function payableUrl(): ?string
    {
        return $this->url();
    }

    /**
     * The public page a customer pays this link on.
     */
    public function url(): string
    {
        return route('payments.pay', $this->token);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * The set price, or null for a customer-entered link -- including one
     * switched from fixed, which keeps its old amount in the column.
     */
    public function fixedAmount(): ?Money
    {
        return $this->amount === null || $this->amount_type !== PaymentLinkAmountType::Fixed
            ? null
            : Money::of($this->amount, $this->currency);
    }

    public function minimum(): ?Money
    {
        return $this->min_amount === null ? null : Money::of($this->min_amount, $this->currency);
    }

    public function maximum(): ?Money
    {
        return $this->max_amount === null ? null : Money::of($this->max_amount, $this->currency);
    }
}
