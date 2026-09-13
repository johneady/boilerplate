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
        Schema::create('contact_submissions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('subject')->nullable();
            $table->text('message');
            // Recorded for abuse handling: the honeypot and rate limiter turn
            // away the bulk of automated submissions, and what gets through is
            // traced by address. 45 characters holds an IPv6 address.
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            // Null means nobody has dealt with this yet, which is what the
            // panel's navigation badge counts.
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();

            $table->index('handled_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_submissions');
    }
};
