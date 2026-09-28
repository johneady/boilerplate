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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            // Set when an administrator deactivates the account: it can no
            // longer sign in, but the row -- and every record naming it as
            // author, recorder or initiator -- stays. How staff are offboarded;
            // see User::deactivate().
            $table->timestamp('deactivated_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();

            // The admin users table default-sorts by name, so the sort that
            // runs on every load has an index to use once the table grows.
            // (Search is deliberately not covered: it issues leading-
            // wildcard LIKEs, which a B-tree index cannot serve.)
            $table->index('name');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
