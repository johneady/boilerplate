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
        Schema::create('refunds', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            // Generated when the refund modal opens (or derived for an
            // automatic refund), so a double-submitted modal finds this row.
            // Also sent to the gateway as its idempotency key.
            $table->string('idempotency_key', 100)->unique();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->string('gateway', 20);
            $table->string('gateway_refund_id')->nullable();
            $table->char('currency', 3);
            $table->unsignedBigInteger('amount');
            // The share of amount that is tax, for the receipt.
            $table->unsignedBigInteger('tax_amount');
            $table->string('reason')->nullable();
            $table->string('status', 20);
            $table->string('failure_reason')->nullable();
            // Null when the refund was issued in the gateway's own dashboard
            // and learned about by webhook.
            $table->foreignId('initiated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->unique(['gateway', 'gateway_refund_id']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
