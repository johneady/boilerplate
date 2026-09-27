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
        Schema::create('posts', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('body')->nullable();
            $table->string('seo_description')->nullable();
            // Null is a draft, a future date is scheduled, a past date is
            // live: one column answers all three questions, and a post goes
            // live at its timestamp with no scheduler needed to move it --
            // the published() scope simply compares against now().
            $table->timestamp('published_at')->nullable()->index();
            // Null on delete rather than cascade: an author's posts outlive
            // their account (the byline falls back to the business name),
            // and a deleted category leaves its posts uncategorised rather
            // than taking them along.
            $table->foreignId('author_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('category_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
