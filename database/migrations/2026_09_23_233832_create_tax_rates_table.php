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
        Schema::create('tax_rates', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 50);
            // A decimal, never a float column: 9.975 (QST) must come back as
            // exactly "9.975". TaxCalculator turns it into integer thousandths
            // of a percent before any arithmetic.
            $table->decimal('percentage', 6, 3);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            // Gateway-side copies of this rate (Stripe TaxRate ids per mode),
            // written by the subscription plan sync.
            $table->json('gateway_refs')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tax_rates');
    }
};
