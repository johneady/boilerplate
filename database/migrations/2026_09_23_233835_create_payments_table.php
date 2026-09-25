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
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            // The public reference: route key for the return, receipt and
            // demo checkout URLs, and the base of every idempotency key sent
            // to a gateway for this payment.
            $table->uuid('uuid')->unique();
            // Derived from the submitted checkout form (or an admin modal), so
            // submitting the same form twice finds this row instead of
            // creating a second payment.
            $table->string('idempotency_key', 100)->unique();
            $table->morphs('payable');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('description');
            $table->string('gateway', 20);
            $table->string('mode', 10);
            $table->string('status', 30);
            $table->string('capture_method', 20);

            // Integer minor units throughout. amount = subtotal + tax_total,
            // fixed when checkout starts. amount_captured and amount_refunded
            // are projections recomputed from payment_transactions -- never
            // written on their own.
            $table->char('currency', 3);
            $table->unsignedBigInteger('subtotal');
            $table->unsignedBigInteger('tax_total');
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('amount_captured')->default(0);
            $table->unsignedBigInteger('amount_refunded')->default(0);
            $table->json('tax_lines');

            $table->string('gateway_checkout_id')->nullable();
            $table->string('gateway_payment_id')->nullable();
            $table->string('gateway_authorization_id')->nullable();
            $table->text('checkout_url')->nullable();

            $table->timestamp('authorized_at')->nullable();
            $table->timestamp('authorization_expires_at')->nullable();
            $table->timestamp('captured_at')->nullable();
            // Claimed with a conditional update (WHERE paid_at IS NULL), so the
            // payable is told it was paid exactly once however many times the
            // payment is reconciled. receipt_sent_at is the same for the email,
            // and expiry_alerted_at for the authorization-expiry warning.
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('receipt_sent_at')->nullable();
            $table->timestamp('expiry_alerted_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('last_reconciled_at')->nullable();

            // Manual payments only: how and when the money arrived, and who
            // recorded it.
            $table->string('manual_method', 30)->nullable();
            $table->string('manual_reference')->nullable();
            $table->date('manual_received_on')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();

            // The Demo gateway's simulated "server-side" state. Nothing else
            // is written here.
            $table->json('metadata')->nullable();
            $table->timestamps();

            // Unique per gateway: a webhook or return naming a gateway id
            // resolves to exactly one payment. MySQL, MariaDB, PostgreSQL and
            // SQLite all allow any number of NULLs in a unique index.
            $table->unique(['gateway', 'gateway_checkout_id']);
            $table->unique(['gateway', 'gateway_payment_id']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
