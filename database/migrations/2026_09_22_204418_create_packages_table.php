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
        Schema::create('packages', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('location');
            $table->string('country');
            $table->string('region');
            $table->string('summary', 255);
            $table->text('description')->nullable();
            // Integer cents, never a float: 19.99 is not representable in
            // binary floating point, and a basket total summed from floats
            // drifts a cent away from what the customer was shown.
            $table->unsignedInteger('price_cents');
            $table->string('resolution');
            $table->unsignedSmallInteger('frame_rate');
            $table->unsignedSmallInteger('clip_count');
            $table->unsignedInteger('duration_seconds');
            // Null means unlimited licences. A number is a hard cap that
            // checkout decrements under a row lock, and 0 is sold out.
            $table->unsignedInteger('stock')->nullable();
            $table->boolean('is_active')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->string('image_path')->nullable();
            $table->string('image_credit')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'region']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('packages');
    }
};
