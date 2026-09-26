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
        Schema::create('media', function (Blueprint $table): void {
            $table->id();

            // The owning record, as a constrained-by-convention morph. Unlike
            // audit_logs -- whose subject is routinely gone, that being the
            // point -- a media row describes a file belonging to something
            // that exists. Nullable so a file can be uploaded before its owner
            // is saved (a create form attaches on save), and so detaching an
            // owner leaves a prunable orphan rather than a dangling pointer.
            $table->nullableMorphs('model');

            // The named slot on the owner: 'avatar', 'gallery', 'attachments'.
            // A model may hold several collections and a collection may hold
            // several files, so this is what HasMedia queries -- never the
            // mime type, which cannot distinguish two image slots.
            $table->string('collection', 64)->default('default');

            // The conversion set from config('images.conversions') applied to
            // this file, or null for a non-image stored as uploaded. Recorded
            // rather than inferred so a file knows how to rebuild itself when
            // a conversion is added later.
            $table->string('conversion_set', 64)->nullable();

            $table->string('disk', 32);

            // The DIRECTORY holding the conversions for an image, or the file
            // path itself for a non-image. See Media::path() -- the difference
            // is what conversions being non-null means.
            $table->string('path');

            // The name the user uploaded it under, kept for display and for a
            // download's Content-Disposition. Never used to build a path: it
            // is attacker-controlled text.
            $table->string('file_name');

            $table->string('mime_type', 128);
            $table->unsignedBigInteger('size');

            // Written conversions as {name: path}. Null for a non-image, which
            // is exactly how the two kinds of row are told apart.
            $table->json('conversions')->nullable();

            // Image geometry, null for non-images and until processing lands.
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();

            // Who uploaded it. nullOnDelete for the audit_logs reason: losing
            // the account must not lose the file.
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            // The lookup HasMedia makes on every render: this model's files in
            // this slot, in order.
            $table->index(['model_type', 'model_id', 'collection', 'sort_order'], 'media_owner_collection_index');

            // Answers "which rows own no record", which is what the orphan
            // prune scans for.
            $table->index('created_at');

            // Explicit rather than left to MySQL's implicit foreign-key index:
            // SQLite and PostgreSQL create none. Serves the media table's
            // eager uploader load (with('uploader') queries by uploaded_by).
            $table->index('uploaded_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
