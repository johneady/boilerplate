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
        Schema::create('articles', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            // App\Voltiva\ArticleTopic: which part of the site the article
            // links back to (cars, batteries, charging, registration...).
            $table->string('topic');
            $table->string('excerpt', 500);
            // Markdown, escaped on render exactly like a page body.
            $table->text('body');
            // An optional car the article is about, linked from its footer.
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->string('image_path')->nullable();
            $table->string('image_credit')->nullable();
            $table->string('seo_description', 255)->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['is_published', 'published_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
