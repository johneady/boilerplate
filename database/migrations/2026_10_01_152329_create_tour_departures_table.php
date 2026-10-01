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
        Schema::create('tour_departures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_id')->constrained()->cascadeOnDelete();
            $table->date('starts_on');
            $table->unsignedInteger('price_per_person_cents')->nullable();
            $table->unsignedSmallInteger('seats_total');
            $table->unsignedSmallInteger('seats_held')->default(0);
            $table->timestamps();

            $table->index(['tour_id', 'starts_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tour_departures');
    }
};
