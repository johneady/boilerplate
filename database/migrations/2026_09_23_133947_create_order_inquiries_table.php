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
        Schema::create('order_inquiries', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 16)->unique();
            $table->string('name');
            $table->string('email');
            $table->string('phone', 50)->nullable();
            $table->string('fulfilment', 16);
            $table->date('needed_on');
            $table->string('delivery_address', 500)->nullable();
            $table->string('occasion', 32)->nullable();
            // A snapshot of each line as the customer saw it -- name, unit
            // and price at the time -- so a later menu edit cannot rewrite
            // what somebody asked for.
            $table->json('items');
            $table->unsignedInteger('estimated_total_cents');
            $table->text('details')->nullable();
            $table->string('allergies', 1000)->nullable();
            $table->string('status', 16)->default('new');
            $table->unsignedInteger('quoted_total_cents')->nullable();
            $table->text('baker_notes')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index(['status', 'needed_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_inquiries');
    }
};
