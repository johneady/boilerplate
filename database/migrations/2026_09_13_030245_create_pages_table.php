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
        Schema::create('pages', function (Blueprint $table): void {
            $table->id();
            // The URL segment this page is served at, so it carries the unique
            // index: two pages sharing a slug would make the catch-all route
            // resolve to whichever the database happened to return first.
            $table->string('slug')->unique();
            $table->string('title');
            // Markdown, not HTML. Rendered through Page::renderedBody(), which
            // escapes any HTML in the source -- see the model's docblock.
            $table->text('body')->nullable();
            // Overrides the site-wide SEO description for this page alone.
            // Nullable rather than defaulting to '' so "never set" and
            // "deliberately blank" stay distinguishable.
            $table->string('seo_description', 255)->nullable();
            // The wide photograph above the page title. Optional: a page
            // without one renders a plain text header.
            $table->string('image_path')->nullable();
            // Unpublished by default: a page created by mistake, or one still
            // being written, must not be reachable the moment it is saved.
            $table->boolean('is_published')->default(false);
            $table->boolean('show_in_footer')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            // The footer query filters on both flags and orders by sort_order,
            // and it runs on every public page render.
            $table->index(['is_published', 'show_in_footer', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
