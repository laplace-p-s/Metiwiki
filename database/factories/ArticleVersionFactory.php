<?php

namespace Database\Factories;

use App\Enums\VersionKind;
use App\Models\Article;
use App\Models\ArticleVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ArticleVersion>
 */
class ArticleVersionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'article_id' => Article::factory(),
            'user_id' => User::factory(),
            'body' => fake()->paragraphs(3, true),
            'summary' => fake()->optional()->sentence(),
            'version_number' => 1,
            'kind' => VersionKind::Edit,
        ];
    }
}
