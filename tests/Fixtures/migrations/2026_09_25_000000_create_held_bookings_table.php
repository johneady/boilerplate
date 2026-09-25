<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The table behind Tests\Fixtures\Payments\HeldBooking.
 *
 * Only ever run against the test database (Tests\TestCase registers this
 * directory with the migrator), so it is created with the rest of the schema,
 * before RefreshDatabase opens the per-test transaction. Created inside that
 * transaction instead, MySQL and MariaDB would commit it implicitly -- ending
 * the transaction, so the rollback the tests rely on never happens.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('held_bookings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('price');
            $table->unsignedInteger('accepted_count')->default(0);
            $table->boolean('fail_on_accept')->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('held_bookings');
    }
};
