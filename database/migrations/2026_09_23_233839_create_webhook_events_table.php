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
        Schema::create('webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->string('gateway', 20);
            $table->string('mode', 10);
            // The gateway's own id for the event, sized to what gateways
            // really issue (see the payments table) rather than the string
            // default: it sits inside the composite unique index below.
            $table->string('event_id', 100);
            $table->string('type');
            $table->json('payload');
            $table->string('status', 20);
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('processed_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            // A redelivered event is inserted with insertOrIgnore and so
            // stored, and processed, once.
            $table->unique(['gateway', 'event_id']);
            // The daily prune deletes by age alone; the fifteen-minute stale
            // sweep dispatched still-Received events by age, and every
            // sibling table got this pair at creation -- this one was
            // originally missed and near-scanned its whole retention.
            $table->index('created_at');
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
