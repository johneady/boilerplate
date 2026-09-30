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
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            // The number the customer quotes, e.g. JR-1042. Filled in from the
            // id right after insert, hence nullable.
            $table->string('number', 16)->nullable()->unique();
            // Set when a signed-in customer ordered, so it shows under "My orders".
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('customer_phone', 32);
            // App\Ordering\Fulfilment: pickup or delivery.
            $table->string('fulfilment', 16);
            $table->string('delivery_address')->nullable();
            $table->dateTime('ready_at');
            $table->text('notes')->nullable();
            // App\Ordering\OrderStatus.
            $table->string('status', 16)->default('new');
            $table->unsignedInteger('subtotal_cents');
            $table->unsignedInteger('delivery_fee_cents')->default(0);
            $table->unsignedInteger('total_cents');
            $table->timestamps();

            $table->index(['status', 'ready_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
