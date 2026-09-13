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
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();

            // The App\Audit\AuditEvent backing value. A string rather than an
            // enum column so adding a case is a code change, not a migration.
            $table->string('event', 64);

            // Nullable throughout: an entry must survive the actor's account
            // being deleted, which is exactly when the trail matters most.
            // nullOnDelete rather than cascade for the same reason -- deleting
            // a user must not delete the record of what they did.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Denormalised copies of the actor, kept because the foreign key
            // above goes null when the account is deleted. Without these an
            // entry would read "somebody did this" forever.
            $table->string('user_name')->nullable();
            $table->string('user_email')->nullable();

            // The subject of a model event, as a plain morph. Deliberately NOT
            // a morphs() relation with a constraint: the row it points at is
            // routinely gone (that is what a delete event is), so this is a
            // label for display, resolved defensively or not at all.
            $table->string('auditable_type')->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();

            // Attribute values before and after the change, already filtered
            // by the Auditable trait -- a password or a 2FA secret must never
            // reach this table. Null for events that are not model changes.
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();

            // Free-form context for events with no before/after: the settings
            // keys that changed, the email a failed sign-in was attempted for.
            $table->json('context')->nullable();

            // 45 characters holds an IPv6 address, matching contact_submissions.
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();

            // created_at only. An audit entry is a statement about a moment and
            // is never edited, so an updated_at column would only ever be a
            // copy of it -- and a row that can be updated invites updating.
            $table->timestamp('created_at')->nullable()->index();

            $table->index('event');
            $table->index('user_id');
            // Answers "what happened to this record", which is the question the
            // panel's relation-free lookups ask.
            $table->index(['auditable_type', 'auditable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
