<?php

namespace App\Livewire\Payments;

use App\Models\PaymentLink;
use App\Models\TaxRate;
use App\Models\User;
use App\Payments\Actions\StartCheckout;
use App\Payments\Data\CheckoutRequest;
use App\Payments\Enums\Gateway;
use App\Payments\Enums\PaymentLinkAmountType;
use App\Payments\Exceptions\GatewayException;
use App\Payments\Exceptions\InvalidPaymentAmount;
use App\Payments\Exceptions\PaymentNotAllowed;
use App\Payments\Money;
use App\Payments\PaymentManager;
use App\Payments\Tax\TaxBreakdown;
use App\Payments\Tax\TaxCalculator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The public page a customer pays a payment link on.
 *
 * Collects who is paying, the amount where the customer chooses it, and the
 * gateway, then hands off to the gateway's hosted checkout. The price never
 * comes from the browser: StartCheckout asks the link for it, and a
 * customer-entered amount is validated there against the link's bounds.
 */
class PayPaymentLink extends Component
{
    /**
     * Checkout attempts one address may start per hour.
     *
     * Every attempt opens a session at the gateway, and a customer-entered
     * amount page is exactly what card-testing scripts look for, so this is
     * kept low enough to make that unrewarding while leaving room for a
     * person who changes their mind a few times.
     */
    private const int MAX_ATTEMPTS_PER_HOUR = 10;

    #[Locked]
    public PaymentLink $paymentLink;

    /**
     * A random token minted when the form renders.
     *
     * Combined with what was submitted into the payment's idempotency key, so
     * a double-clicked Pay button -- or a slow network retrying the request --
     * finds the payment the first click created instead of opening a second.
     */
    #[Locked]
    public string $formToken = '';

    public string $name = '';

    public string $email = '';

    /**
     * The amount the customer entered, as typed, for a customer-entered link.
     */
    public string $amount = '';

    public string $gateway = '';

    public function mount(PaymentLink $paymentLink, PaymentManager $payments): void
    {
        abort_unless($paymentLink->is_active, 404);

        $this->paymentLink = $paymentLink;
        $this->formToken = Str::random(40);
        $this->gateway = $payments->checkoutGateways()[0]->value ?? '';

        if (auth()->check()) {
            $this->name = (string) auth()->user()?->name;
            $this->email = (string) auth()->user()?->email;
        }
    }

    /**
     * The gateways the customer may choose between.
     *
     * @return list<Gateway>
     */
    #[Computed]
    public function gateways(): array
    {
        return app(PaymentManager::class)->checkoutGateways();
    }

    /**
     * The subtotal, taxes and total for what is currently entered, or null
     * while a customer-entered amount is blank or unreadable.
     */
    #[Computed]
    public function breakdown(): ?TaxBreakdown
    {
        try {
            $subtotal = $this->paymentLink->amountDue($this->offeredAmount());
        } catch (InvalidPaymentAmount|InvalidArgumentException) {
            return null;
        }

        return app(TaxCalculator::class)->calculate(
            $subtotal,
            $this->paymentLink->isTaxable() ? TaxRate::query()->active()->get()->map->toCalculatorRate() : [],
        );
    }

    public function pay(StartCheckout $checkout): void
    {
        $this->ensureIsNotRateLimited();

        // The currency's own decimal places decide the fraction the customer
        // may type, so a zero- or three-decimal currency is not rejected by
        // a hardcoded two (see Currency::decimals()).
        $decimals = $this->paymentLink->currency->decimals();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'gateway' => ['required', Rule::in(array_map(fn (Gateway $gateway): string => $gateway->value, $this->gateways()))],
            'amount' => $this->isCustomerEntered()
                ? ['required', 'regex:/^\d{1,9}('.($decimals > 0 ? '\.\d{1,'.$decimals.'}' : '').')?$/']
                : ['nullable'],
        ], [
            'amount.regex' => __('Enter an amount such as 25 or 25.50.'),
        ]);

        RateLimiter::increment($this->rateLimitKey(), 3600);

        $gateway = Gateway::from($validated['gateway']);
        $offered = $this->offeredAmount();

        try {
            $payment = $checkout->handle($this->paymentLink, new CheckoutRequest(
                gateway: $gateway,
                customerName: $validated['name'],
                customerEmail: $validated['email'],
                idempotencyKey: $this->idempotencyKey($gateway, $offered, $validated['email']),
                offeredAmount: $offered,
                userId: $this->signedInUserId(),
            ));
        } catch (InvalidPaymentAmount $e) {
            throw ValidationException::withMessages(['amount' => $e->getMessage()]);
        } catch (PaymentNotAllowed $e) {
            throw ValidationException::withMessages(['gateway' => $e->getMessage()]);
        } catch (GatewayException $e) {
            report($e);

            $this->couldNotStart();
        }

        if ($payment->checkout_url === null) {
            $this->couldNotStart();
        }

        $this->redirect($payment->checkout_url);
    }

    public function isCustomerEntered(): bool
    {
        return $this->paymentLink->amount_type === PaymentLinkAmountType::CustomerEntered;
    }

    public function render(): View
    {
        return view('livewire.payments.pay-payment-link')
            ->layout('layouts::public', [
                'title' => $this->paymentLink->title,
                'description' => null,
            ]);
    }

    /**
     * The typed amount as money, null while blank, and throwing on text that
     * is not an amount.
     */
    private function offeredAmount(): ?Money
    {
        if (! $this->isCustomerEntered() || trim($this->amount) === '') {
            return null;
        }

        return Money::fromDecimal($this->amount, $this->paymentLink->currency);
    }

    /**
     * The same form submitted with the same choices maps to the same payment;
     * changing the gateway, amount or email after a failed attempt starts a
     * new one.
     */
    private function idempotencyKey(Gateway $gateway, ?Money $offered, string $email): string
    {
        return 'checkout:'.hash('sha256', implode('|', [
            $this->formToken,
            $this->paymentLink->id,
            $gateway->value,
            $offered->amount ?? '',
            Str::lower($email),
        ]));
    }

    /**
     * Tell the customer the checkout could not be opened, and let a retry
     * start afresh.
     *
     * The attempt that failed is recorded as a failed payment under this
     * form's idempotency key, so pressing Pay again with the same key would
     * only find it again. A new token makes the retry a new attempt; a
     * double-click still shares one, since both land before this runs.
     */
    private function couldNotStart(): never
    {
        $this->formToken = Str::random(40);

        throw ValidationException::withMessages([
            'gateway' => __('The payment could not be started. Please try again, or choose another payment method.'),
        ]);
    }

    private function signedInUserId(): ?int
    {
        $user = auth()->user();

        return $user instanceof User ? $user->id : null;
    }

    private function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->rateLimitKey(), self::MAX_ATTEMPTS_PER_HOUR)) {
            return;
        }

        throw ValidationException::withMessages([
            'gateway' => __('Too many payment attempts. Please try again later.'),
        ]);
    }

    private function rateLimitKey(): string
    {
        return 'payment-checkout:'.request()->ip();
    }
}
