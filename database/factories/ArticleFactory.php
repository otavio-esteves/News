<?php

namespace Database\Factories;

use App\Enums\ArticleStatus;
use App\Models\Article;
use App\Models\Source;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Article> */
class ArticleFactory extends Factory
{
    protected $model = Article::class;

    public function definition(): array
    {
        $url = fake()->unique()->url();

        return [
            'source_id' => Source::factory(),
            'original_url' => $url,
            'canonical_url' => $url,
            'title' => fake()->sentence(),
            'description' => fake()->sentence(),
            'published_at' => now(),
            'discovered_at' => now(),
            'status' => ArticleStatus::Discovered,
        ];
    }
}
