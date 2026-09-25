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
        // The append-only ledger. Rows are inserted and never updated or
        // deleted (App\Models\PaymentTransaction throws on both), which is
        // why there is no updated_at. A correction is a new row.
        Schema::create('payment_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->string('type', 20);
            // Signed minor units: positive for a charge, negative for a refund.
            $table->bigInteger('amount');
            $table->char('currency', 3);
            $table->string('gateway', 20);
            // The gateway's own id for the movement (a Stripe charge, a PayPal
            // capture, a refund). Unique per gateway, which is what makes the
            // same capture seen by the return URL AND the webhook record once.
            $table->string('gateway_transaction_id');
            $table->string('source', 20);
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->nullable();

            $table->unique(['gateway', 'gateway_transaction_id']);
            // Explicit rather than left to MySQL's implicit foreign-key index:
            // SQLite and PostgreSQL create none, and the reconcile projections
            // count these rows under the payment's row lock.
            $table->index('payment_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
