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
        Schema::create('payment_links', function (Blueprint $table): void {
            $table->id();
            // The public, unguessable part of the /pay/{token} URL.
            $table->string('token', 64)->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('amount_type', 30);
            // Integer minor units (cents). Null for a customer-entered amount.
            $table->unsignedBigInteger('amount')->nullable();
            $table->unsignedBigInteger('min_amount')->nullable();
            $table->unsignedBigInteger('max_amount')->nullable();
            $table->char('currency', 3);
            $table->boolean('taxable')->default(false);
            $table->string('usage', 30);
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            // The payment that settled a single-use link. Written under a row
            // lock on this link, which is what stops two concurrent checkouts
            // both claiming it (see PaymentLink::acceptPayment()). No foreign
            // key: payments is created after this table, and a payment is
            // never deleted anyway.
            $table->unsignedBigInteger('settled_payment_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // For the reverse lookup "which link did this payment settle",
            // which the admin panel makes per payment.
            $table->index('settled_payment_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_links');
    }
};
