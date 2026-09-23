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
        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            // Nulled rather than cascaded when a package is deleted: the order
            // is a record of a sale, and it keeps the title and price it was
            // sold at in the snapshot columns below.
            $table->foreignId('package_id')->nullable()->constrained()->nullOnDelete();
            $table->string('package_title');
            $table->unsignedInteger('price_cents');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
