<?php

namespace App\Livewire\Settings;

use App\Models\Payment;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Models\User;
use App\Payments\Actions\CancelSubscription;
use App\Payments\Actions\ResumeSubscription;
use App\Payments\Actions\SwapSubscriptionPlan;
use App\Payments\Enums\SubscriptionStatus;
use App\Payments\Exceptions\GatewayException;
use App\Payments\Exceptions\PaymentNotAllowed;
use App\Payments\PaymentManager;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The signed-in user's subscription and payments.
 *
 * Every action works on the user's own current subscription, read afresh
 * from the database on each request -- never on an id sent from the browser
 * -- so one customer can never act on another's subscription.
 */
#[Title('Billing')]
class Billing extends Component
{
    /**
     * The price chosen in the "change plan" select.
     */
    public string $newPriceId = '';

    /**
     * What the last action did, shown above the subscription.
     */
    public string $status = '';

    #[Computed]
    public function subscription(): ?Subscription
    {
        return $this->user()->currentSubscription();
    }

    /**
     * Whether a subscription checkout the user has finished is still waiting
     * for the gateway to confirm it. Such a subscription has not started, so
     * it is not the subscription() this page is about.
     */
    #[Computed]
    public function settingUp(): bool
    {
        return Subscription::query()
            ->where('active_user_id', $this->user()->id)
            ->where('status', SubscriptionStatus::Incomplete->value)
            ->exists();
    }

    /**
     * The user's payments that took money, newest first.
     *
     * @return Collection<int, Payment>
     */
    #[Computed]
    public function payments(): Collection
    {
        return Payment::query()
            ->where('user_id', $this->user()->id)
            ->whereNotNull('paid_at')
            ->latest('paid_at')
            ->limit(24)
            ->get();
    }

    /**
     * The prices the subscription can move to: every other active price, in
     * its currency, on an active plan.
     *
     * @return Collection<int, PlanPrice>
     */
    #[Computed]
    public function swapOptions(): Collection
    {
        $subscription = $this->subscription();

        if ($subscription === null || ! in_array($subscription->status, [SubscriptionStatus::Trialing, SubscriptionStatus::Active], true) || $subscription->cancel_at_period_end) {
            return new Collection;
        }

        return PlanPrice::query()
            ->with('plan')
            ->where('is_active', true)
            ->where('currency', $subscription->currency->value)
            ->whereKeyNot($subscription->plan_price_id)
            ->whereHas('plan', fn ($plan) => $plan->where('is_active', true))
            ->get()
            ->sortBy(fn (PlanPrice $price): string => sprintf('%05d-%s-%012d', $price->plan->sort_order ?? 0, $price->interval->value, $price->amount))
            ->values();
    }

    #[Computed]
    public function graceDays(): int
    {
        return app(PaymentManager::class)->graceDays();
    }

    public function cancel(CancelSubscription $cancel): void
    {
        $this->act(function () use ($cancel): void {
            $cancel->handle($this->requireSubscription(), atPeriodEnd: true);
            $this->status = __('Your subscription will end when the current period does.');
        });
    }

    public function resume(ResumeSubscription $resume): void
    {
        $this->act(function () use ($resume): void {
            $resume->handle($this->requireSubscription());
            $this->status = __('Your subscription will continue.');
        });
    }

    public function swap(SwapSubscriptionPlan $swap): void
    {
        $price = $this->swapOptions()->firstWhere('id', (int) $this->newPriceId);

        if ($price === null) {
            throw ValidationException::withMessages(['newPriceId' => __('Choose a plan to change to.')]);
        }

        $this->act(function () use ($swap, $price): void {
            $approvalUrl = $swap->handle($this->requireSubscription(), $price);

            if ($approvalUrl !== null) {
                $this->redirect($approvalUrl);

                return;
            }

            $this->newPriceId = '';
            $this->status = __('Your plan has been changed.');
        });
    }

    public function updatePaymentMethod(PaymentManager $payments): void
    {
        $this->act(function () use ($payments): void {
            $subscription = $this->requireSubscription();
            $url = $payments->subscriptionDriver($subscription->gateway, $subscription->mode)
                ->paymentMethodUrl($subscription, route('billing.edit'));

            if ($url === null) {
                throw new PaymentNotAllowed(__('The payment method for this subscription cannot be changed online.'));
            }

            $this->redirect($url);
        });
    }

    public function render(): View
    {
        return view('livewire.settings.billing');
    }

    /**
     * Run an action, turning a refusal into a message on the page.
     *
     * @param  \Closure(): void  $action
     */
    private function act(\Closure $action): void
    {
        try {
            $action();
        } catch (PaymentNotAllowed $e) {
            throw ValidationException::withMessages(['billing' => $e->getMessage()]);
        } catch (GatewayException $e) {
            report($e);

            throw ValidationException::withMessages(['billing' => __('That could not be done just now. Please try again in a few minutes.')]);
        } finally {
            unset($this->subscription, $this->swapOptions);
        }
    }

    private function requireSubscription(): Subscription
    {
        return $this->subscription() ?? throw new PaymentNotAllowed(__('You have no subscription.'));
    }

    private function user(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
