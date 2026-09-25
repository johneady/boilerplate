<?php

use App\Payments\Enums\GatewayMode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Named gap-free counters, such as receipt numbers. A row per counter,
        // locked FOR UPDATE while the next value is taken, so two
        // transactions can never be handed the same number -- and taken
        // inside the caller's transaction, so a rollback returns the number
        // rather than leaving a gap. A database auto-increment can offer
        // neither: it is not transactional and skips values on rollback.
        Schema::create('sequences', function (Blueprint $table): void {
            $table->string('name', 50)->primary();
            $table->unsignedBigInteger('value')->default(0);
        });

        // A receipt series per payments mode. Created here rather than on
        // first use: on MySQL two transactions both finding the row missing
        // take gap locks and deadlock on the insert that follows.
        DB::table('sequences')->insert([
            ['name' => 'receipt:'.GatewayMode::Sandbox->value, 'value' => 0],
            ['name' => 'receipt:'.GatewayMode::Live->value, 'value' => 0],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sequences');
    }
};
