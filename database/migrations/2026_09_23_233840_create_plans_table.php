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
        Schema::create('plans', function (Blueprint $table): void {
            $table->id();
            // What code checks access against: $user->subscribed('pro') and
            // the subscribed:pro middleware. Never shown to customers.
            $table->string('key', 50)->unique();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->json('features');
            $table->unsignedSmallInteger('trial_days')->default(0);
            $table->boolean('taxable')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            // The gateway-side product for this plan, per gateway and mode,
            // written by the plan sync.
            $table->json('gateway_refs')->nullable();
            $table->timestamps();
        });

        Schema::create('plan_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            // Fixed once created: a price change is a new row, so existing
            // subscribers keep what they signed up for.
            $table->char('currency', 3);
            $table->unsignedBigInteger('amount');
            $table->string('interval', 10);
            $table->unsignedSmallInteger('interval_count')->default(1);
            $table->boolean('is_active')->default(true);
            // The gateway-side price (a Stripe Price, a PayPal billing plan)
            // per gateway and mode, written by the plan sync.
            $table->json('gateway_refs')->nullable();
            $table->timestamps();

            $table->index(['plan_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plan_prices');
        Schema::dropIfExists('plans');
    }
};
