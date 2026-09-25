<?php

namespace App\Livewire\Payments;

use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Models\User;
use App\Payments\Actions\StartSubscription;
use App\Payments\Enums\Gateway;
use App\Payments\Exceptions\GatewayException;
use App\Payments\Exceptions\PaymentNotAllowed;
use App\Payments\PaymentManager;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The public plan list, and where a signed-in customer subscribes.
 *
 * Anyone can compare plans; subscribing needs a verified account, because a
 * subscription belongs to a user. Only prices in the installation's currency
 * are offered, and the price charged is always the PlanPrice's own -- nothing
 * about the amount comes from the browser.
 */
class Pricing extends Component
{
    /**
     * Subscribe attempts one account may start per hour. Each opens a
     * checkout at the gateway.
     */
    private const int MAX_ATTEMPTS_PER_HOUR = 10;

    /**
     * A random token minted when the page renders, combined into the
     * subscription's idempotency key so a double-clicked Subscribe opens one
     * checkout.
     */
    #[Locked]
    public string $formToken = '';

    public string $gateway = '';

    public function mount(PaymentManager $payments): void
    {
        $this->formToken = Str::random(40);
        $this->gateway = $payments->subscriptionGateways()[0]->value ?? '';
    }

    /**
     * Active plans with at least one active price in the installation's
     * currency, each with those prices loaded.
     *
     * @return Collection<int, Plan>
     */
    #[Computed]
    public function plans(): Collection
    {
        $currency = app(PaymentManager::class)->currency();

        return Plan::query()
            ->active()
            ->with(['prices' => fn ($query) => $query->where('is_active', true)->where('currency', $currency->value)->orderBy('interval')->orderBy('amount')])
            ->get()
            ->filter(fn (Plan $plan): bool => $plan->prices->isNotEmpty())
            ->values();
    }

    /**
     * @return list<Gateway>
     */
    #[Computed]
    public function gateways(): array
    {
        return app(PaymentManager::class)->subscriptionGateways();
    }

    /**
     * Whether the plans' free trials apply to whoever is looking: anyone not
     * signed in, or a customer who has never subscribed. Anyone else would
     * not get one (User::isEligibleForTrial()), so is not offered one.
     */
    #[Computed]
    public function offersTrial(): bool
    {
        $user = auth()->user();

        return ! $user instanceof User || $user->isEligibleForTrial(app(PaymentManager::class)->mode());
    }

    /**
     * The signed-in user's running subscription, if any.
     */
    #[Computed]
    public function currentSubscription(): ?Subscription
    {
        $user = auth()->user();
        $subscription = $user instanceof User ? $user->currentSubscription() : null;

        return $subscription?->status->isLive() ? $subscription : null;
    }

    public function subscribe(int $priceId, StartSubscription $subscriptions): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            session()->put('url.intended', route('payments.pricing'));
            $this->redirect(route('login'));

            return;
        }

        if (! $user->hasVerifiedEmail()) {
            $this->redirect(route('verification.notice'));

            return;
        }

        $this->ensureIsNotRateLimited($user);

        $this->validate([
            'gateway' => ['required', Rule::in(array_map(fn (Gateway $gateway): string => $gateway->value, $this->gateways()))],
        ]);

        $price = PlanPrice::query()->whereKey($priceId)->where('is_active', true)->with('plan')->first();

        if ($price === null) {
            throw ValidationException::withMessages(['subscribe' => __('That plan is not available.')]);
        }

        RateLimiter::increment($this->rateLimitKey($user), 3600);

        $gateway = Gateway::from($this->gateway);

        try {
            $subscription = $subscriptions->handle($user, $price, $gateway, 'subscribe:'.hash('sha256', implode('|', [
                $this->formToken, $user->id, $price->id, $gateway->value,
            ])));
        } catch (PaymentNotAllowed $e) {
            throw ValidationException::withMessages(['subscribe' => $e->getMessage()]);
        } catch (GatewayException $e) {
            report($e);
            // The attempt that failed is recorded under this token; a new one
            // makes pressing Subscribe again a new attempt.
            $this->formToken = Str::random(40);

            throw ValidationException::withMessages([
                'subscribe' => __('The subscription could not be started. Please try again, or choose another payment method.'),
            ]);
        }

        if ($subscription->checkout_url === null) {
            throw ValidationException::withMessages(['subscribe' => __('You already have a subscription. Change or cancel it from your billing page.')]);
        }

        $this->redirect($subscription->checkout_url);
    }

    public function render(): View
    {
        return view('livewire.payments.pricing')
            ->layout('layouts::public', [
                'title' => __('Pricing'),
                'description' => null,
            ]);
    }

    private function ensureIsNotRateLimited(User $user): void
    {
        if (RateLimiter::tooManyAttempts($this->rateLimitKey($user), self::MAX_ATTEMPTS_PER_HOUR)) {
            throw ValidationException::withMessages(['subscribe' => __('Too many attempts. Please try again later.')]);
        }
    }

    private function rateLimitKey(User $user): string
    {
        return 'subscription-checkout:'.$user->id;
    }
}
