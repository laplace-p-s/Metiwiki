<?php

namespace Database\Factories;

use App\Models\Article;
use App\Models\ArticleLink;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ArticleLink>
 */
class ArticleLinkFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'from_article_id' => Article::factory(),
            'to_title_key' => Article::titleKey(fake()->slug(3)),
        ];
    }
}
