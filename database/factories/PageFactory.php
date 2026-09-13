<?php

namespace Database\Factories;

use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Unpublished by default, matching the column default: a test that wants a
     * publicly reachable page says so with ->published(), which keeps the
     * draft-is-hidden assertions honest.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // words() is declared string|array and returns the array form without
        // the `true`, so the words are joined here rather than cast: that holds
        // whichever arm comes back, and a cast cannot narrow the union anyway.
        $words = fake()->unique()->words(3);

        $title = implode(' ', is_array($words) ? $words : [$words]);

        return [
            'slug' => Str::slug($title),
            'title' => Str::title($title),
            'body' => '## '.fake()->sentence()."\n\n".fake()->paragraph(),
            'seo_description' => fake()->sentence(),
            'is_published' => false,
            'show_in_footer' => true,
            'sort_order' => 0,
        ];
    }

    /**
     * Indicate that the page is visible to the public.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_published' => true,
        ]);
    }

    /**
     * Indicate that the page is not linked in the footer.
     */
    public function hiddenFromFooter(): static
    {
        return $this->state(fn (array $attributes): array => [
            'show_in_footer' => false,
        ]);
    }
}
