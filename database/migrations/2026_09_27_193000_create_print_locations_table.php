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
        Schema::create('print_locations', function (Blueprint $table): void {
            $table->id();
            // The URL segment the in-store QR code encodes, so it carries the
            // unique index: two counters sharing a slug would make the QR
            // landing route resolve to whichever the database returned first.
            $table->string('slug')->unique();
            $table->string('name');
            // Where the pickup happens, shown on the customer's confirmation
            // screen and on the fulfillment console.
            $table->string('address');
            // An inactive location's QR route answers 404 rather than quietly
            // sending orders nobody is standing at a counter to receive.
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('print_locations');
    }
};
