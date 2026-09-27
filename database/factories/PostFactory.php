<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * A draft (no publish date) by default, matching the column's meaning: a
     * test that wants a publicly reachable post says so with ->published(),
     * which keeps the draft-is-hidden assertions honest.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // words() is declared string|array and returns the array form without
        // the `true`, so the words are joined here rather than cast -- see
        // PageFactory for the reasoning. paragraphs() is the same.
        $words = fake()->unique()->words(4);

        $title = implode(' ', is_array($words) ? $words : [$words]);

        $paragraphs = fake()->paragraphs(3, true);

        return [
            'title' => Str::title($title),
            'slug' => Str::slug($title),
            'body' => '## '.fake()->sentence()."\n\n".(
                is_string($paragraphs) ? $paragraphs : implode("\n\n", $paragraphs)
            ),
            'seo_description' => fake()->sentence(),
            'published_at' => null,
            'author_id' => User::factory(),
            'category_id' => null,
        ];
    }

    /**
     * Indicate that the post is visible to the public.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes): array => [
            'published_at' => now()->subDays(1),
        ]);
    }

    /**
     * Indicate that the post is scheduled to go live in the future.
     */
    public function scheduled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'published_at' => now()->addDays(1),
        ]);
    }

    /**
     * Indicate that the post belongs to a category.
     */
    public function inCategory(?Category $category = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'category_id' => $category !== null ? $category->id : Category::factory(),
        ]);
    }

    /**
     * Indicate that the post has no author, as a deleted user's posts read.
     */
    public function withoutAuthor(): static
    {
        return $this->state(fn (array $attributes): array => [
            'author_id' => null,
        ]);
    }
}
