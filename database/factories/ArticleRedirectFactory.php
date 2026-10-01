<?php

namespace Database\Factories;

use App\Models\Article;
use App\Models\ArticleRedirect;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ArticleRedirect>
 */
class ArticleRedirectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'old_title_key' => Article::titleKey(fake()->unique()->slug(3)),
            'article_id' => Article::factory(),
        ];
    }
}
