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
        Schema::create('print_order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('print_order_id')->constrained('print_orders')->cascadeOnDelete();
            // One row per photo: the media row holding the re-encoded image,
            // and how many prints of it the customer asked for.
            $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();
            $table->unsignedTinyInteger('quantity');
            // Which of the store's printers this photo was last sent to, and
            // when. Null until a staff member sends it, which is what lets the
            // console show an unprinted photo differently from a printed one.
            $table->string('printer', 32)->nullable();
            $table->timestamp('printed_at')->nullable();
            $table->timestamps();

            $table->index(['print_order_id', 'printed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('print_order_items');
    }
};
