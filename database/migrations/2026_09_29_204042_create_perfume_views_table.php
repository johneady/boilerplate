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
        // One row per perfume per day rather than one per visit: the owner
        // needs trends, not a clickstream, and a busy page then costs one
        // indexed upsert per view instead of an ever-growing log table.
        Schema::create('perfume_views', function (Blueprint $table): void {
            $table->foreignId('perfume_id')->constrained()->cascadeOnDelete();
            $table->date('viewed_on')->index();
            $table->unsignedInteger('views')->default(0);

            $table->primary(['perfume_id', 'viewed_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('perfume_views');
    }
};
