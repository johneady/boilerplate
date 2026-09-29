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
        Schema::create('perfumes', function (Blueprint $table): void {
            $table->id();
            // The row's key in the upstream data source (the Lovable/Supabase
            // export, then every later refresh file). Imports upsert on it, so
            // a refresh updates a perfume in place instead of duplicating it,
            // and its followers and view history survive the refresh.
            $table->string('external_id')->unique();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedSmallInteger('release_year')->nullable()->index();
            $table->string('concentration')->nullable();
            $table->string('gender')->nullable()->index();
            $table->string('family')->nullable()->index();
            $table->string('perfumer')->nullable();
            $table->json('top_notes')->nullable();
            $table->json('heart_notes')->nullable();
            $table->json('base_notes')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['brand_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('perfumes');
    }
};
