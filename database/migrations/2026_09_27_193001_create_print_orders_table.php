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
        Schema::create('print_orders', function (Blueprint $table): void {
            $table->id();
            // The short code the whole fulfillment flow keys on: the customer
            // reads it off their phone, the staff member reads it off the
            // console, and neither ever says a database id out loud.
            $table->string('code', 12)->unique();
            // How the order arrived: a QR scan at the counter (pay at pickup,
            // no payment step) or from the website (paid, posted out).
            $table->string('channel', 16);
            // The counter whose QR code was scanned. Null for remote orders,
            // which belong to no location until they are printed.
            $table->foreignId('print_location_id')->nullable()->constrained('print_locations')->nullOnDelete();
            // received -> printing -> ready -> completed, plus cancelled for
            // the staff-side escape hatch. Stored as the enum's string value.
            $table->string('status', 16)->default('received');
            // pay_at_counter for in-store orders, paid once the remote flow's
            // checkout succeeds. No "unpaid" state: an in-store order is never
            // confronted with a payment request, it is simply owed at pickup.
            $table->string('payment_status', 16)->default('pay_at_counter');
            $table->string('customer_name');
            $table->string('customer_email')->nullable();
            $table->string('customer_phone')->nullable();
            // Where a remote order is posted. Null for in-store orders, which
            // are picked up rather than delivered.
            $table->text('mailing_address')->nullable();
            // The pricing snapshot in minor units at the moment the order was
            // placed: what the customer agreed to and paid, kept on the order
            // so a later settings change cannot rewrite history.
            $table->unsignedInteger('prints_total_cents');
            $table->unsignedInteger('list_total_cents');
            $table->unsignedInteger('savings_cents')->default(0);
            $table->timestamps();

            // The fulfillment console's two working views: the queue ordered
            // oldest-first within a status, and the recent history beside it.
            $table->index(['status', 'created_at']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('print_orders');
    }
};
