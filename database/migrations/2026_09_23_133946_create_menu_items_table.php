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
        Schema::create('menu_items', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('category');
            $table->string('description', 500);
            // Integer cents, never a float: an estimate summed from floats
            // drifts a cent away from what the customer was shown.
            $table->unsignedInteger('price_cents');
            // What the price buys: "per loaf", "half dozen", "9-inch pie".
            $table->string('price_unit', 64);
            $table->string('serves', 64)->nullable();
            $table->json('dietary')->nullable();
            // The minimum notice this item needs. The order form takes the
            // largest across the items chosen as the earliest date offered.
            $table->unsignedTinyInteger('notice_days')->default(2);
            $table->boolean('is_available')->default(true);
            $table->boolean('is_seasonal')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->string('image_path')->nullable();
            $table->string('image_credit')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_available', 'category']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};
