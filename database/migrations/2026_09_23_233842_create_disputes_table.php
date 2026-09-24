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
        Schema::create('disputes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->string('gateway', 20);
            $table->string('mode', 10);
            $table->string('gateway_dispute_id');
            // What the customer's bank or PayPal is claiming back, which can
            // differ from the payment (a partial claim, a currency swing).
            $table->char('currency', 3);
            $table->unsignedBigInteger('amount');
            $table->string('reason')->nullable();
            $table->string('status', 20);
            // When the gateway stops accepting evidence. Null once decided,
            // or for an inquiry that asks for none.
            $table->timestamp('evidence_due_by')->nullable();
            $table->timestamp('closed_at')->nullable();
            // Claimed with a conditional update so operators are told once.
            $table->timestamp('opened_notified_at')->nullable();
            $table->timestamps();

            $table->unique(['gateway', 'gateway_dispute_id']);
            $table->index(['status', 'mode']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('disputes');
    }
};
