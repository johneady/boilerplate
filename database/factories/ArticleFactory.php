<?php

namespace Database\Factories;

use App\Models\Article;
use App\Voltiva\ArticleTopic;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Article>
 */
class ArticleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = rtrim(fake()->unique()->sentence(5), '.');

        return [
            'slug' => Str::slug($title),
            'title' => $title,
            'topic' => ArticleTopic::Batteries,
            'excerpt' => fake()->sentence(15),
            'body' => '## '.fake()->sentence()."\n\n".fake()->paragraph()."\n\n".fake()->paragraph(),
            'is_published' => false,
            'published_at' => now()->subDay(),
        ];
    }

    /**
     * Indicate that the article is visible to the public.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes): array => ['is_published' => true]);
    }
}
