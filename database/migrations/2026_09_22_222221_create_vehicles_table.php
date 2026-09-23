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
        Schema::create('vehicles', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            // App\Voltiva\VehicleCategory: the EU class (L6e or L7e), which
            // decides the licence a buyer needs and so drives half the page.
            $table->string('category');
            $table->string('tagline');
            $table->string('summary', 500);
            $table->text('description')->nullable();
            // Integer cents, never a float, so a displayed price and a finance
            // estimate built from it can never drift a cent apart.
            $table->unsignedInteger('price_cents');
            $table->unsignedInteger('monthly_from_cents')->nullable();

            // Every figure the compare page reads. Stored once, here, so the
            // product page and the comparison can never disagree.
            $table->unsignedSmallInteger('top_speed_kmh');
            $table->unsignedSmallInteger('range_km');
            $table->unsignedSmallInteger('battery_voltage');
            $table->unsignedSmallInteger('battery_capacity_ah');
            $table->decimal('battery_kwh', 5, 1);
            $table->string('battery_chemistry');
            $table->decimal('charge_hours', 4, 1);
            $table->decimal('motor_kw', 5, 1);
            $table->unsignedTinyInteger('seats');
            $table->unsignedSmallInteger('length_mm');
            $table->unsignedSmallInteger('width_mm');
            $table->unsignedSmallInteger('height_mm');
            $table->unsignedSmallInteger('kerb_weight_kg');
            $table->unsignedTinyInteger('warranty_years')->default(2);
            $table->unsignedTinyInteger('battery_warranty_years')->default(5);

            // Lists the editor fills with repeaters: plain strings for the
            // equipment, {title, body} for benefits, {question, answer} for FAQs.
            $table->json('equipment')->nullable();
            $table->json('key_benefits')->nullable();
            $table->text('comfort')->nullable();
            $table->text('safety')->nullable();
            $table->json('faqs')->nullable();

            $table->string('image_path')->nullable();
            $table->json('gallery')->nullable();
            $table->string('video_url')->nullable();
            $table->string('image_credit')->nullable();

            $table->string('seo_description', 255)->nullable();
            $table->boolean('is_published')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_published', 'category', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
