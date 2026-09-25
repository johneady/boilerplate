<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('billing_customers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('gateway', 20);
            $table->string('mode', 10);
            $table->string('gateway_customer_id');
            $table->timestamps();

            $table->unique(['user_id', 'gateway', 'mode']);
            $table->unique(['gateway', 'mode', 'gateway_customer_id']);
        });

        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            // The public reference: route key for the return URLs, and the
            // base of every idempotency key sent to a gateway for it.
            $table->uuid('uuid')->unique();
            $table->string('idempotency_key', 100)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // Equal to user_id while the subscription is live (incomplete,
            // trialing, active or past due) and null once it has ended. The
            // unique index is what enforces one live subscription per user on
            // MySQL, MariaDB and SQLite alike -- MySQL has no partial unique
            // index -- so two concurrent subscribe attempts cannot both win.
            $table->foreignId('active_user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->foreignId('plan_price_id')->constrained()->restrictOnDelete();
            // A plan change the customer has still to approve at the gateway
            // (PayPal's revise flow).
            $table->foreignId('pending_plan_price_id')->nullable()->constrained('plan_prices')->nullOnDelete();
            $table->string('gateway', 20);
            $table->string('mode', 10);
            $table->string('status', 20);
            $table->char('currency', 3);
            // The free trial this subscription was offered, fixed when it is
            // created: the plan's own, or none for a customer who has
            // subscribed before in this mode, so a trial cannot be had again
            // by cancelling and starting over. Every gateway reads it here.
            $table->unsignedSmallInteger('trial_days')->default(0);

            $table->string('gateway_subscription_id')->nullable();
            $table->string('gateway_customer_id')->nullable();
            $table->string('gateway_checkout_id')->nullable();
            $table->text('checkout_url')->nullable();

            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->boolean('cancel_at_period_end')->default(false);
            $table->timestamp('canceled_at')->nullable();
            // When access stops: the period end for a cancellation scheduled
            // at period end, the moment it ended otherwise.
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('past_due_since')->nullable();

            // Claimed with a conditional update, so each lifecycle email is
            // sent once however many times the subscription is reconciled.
            $table->timestamp('started_notified_at')->nullable();
            $table->timestamp('trial_reminder_sent_at')->nullable();
            $table->timestamp('ended_notified_at')->nullable();
            // Claimed the first time this subscription is found running at
            // the gateway alongside the user's other live one, so operators
            // are told once about a customer being billed twice.
            $table->timestamp('duplicate_alerted_at')->nullable();
            $table->timestamp('last_reconciled_at')->nullable();

            // The Demo gateway's simulated "server-side" state.
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['gateway', 'gateway_subscription_id']);
            $table->unique(['gateway', 'gateway_checkout_id']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('billing_customers');
    }
};
