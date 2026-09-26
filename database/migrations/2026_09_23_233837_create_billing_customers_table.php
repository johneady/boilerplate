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
        // A gateway's customer object for one user, created on first
        // subscribe so repeat checkouts and the billing portal have one to
        // attach to. Kept as its own table (and migration) rather than a
        // column on users: a user may have one per gateway AND per mode.
        Schema::create('billing_customers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('gateway', 20);
            $table->string('mode', 10);
            // Sized like the payments table's gateway ids: real customer ids
            // are short, and this sits inside a composite unique index.
            $table->string('gateway_customer_id', 100);
            $table->timestamps();

            $table->unique(['user_id', 'gateway', 'mode']);
            $table->unique(['gateway', 'mode', 'gateway_customer_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing_customers');
    }
};
