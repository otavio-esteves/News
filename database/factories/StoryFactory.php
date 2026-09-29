<?php

namespace Database\Factories;

use App\Enums\StoryCategory;
use App\Enums\StoryStatus;
use App\Models\Story;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Story> */
class StoryFactory extends Factory
{
    protected $model = Story::class;

    public function definition(): array
    {
        return [
            'status' => StoryStatus::Draft,
            'first_seen_at' => now(),
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'slug' => fake()->unique()->slug(),
            'title' => fake()->sentence(),
            'category' => fake()->randomElement(StoryCategory::cases()),
            'status' => StoryStatus::Published,
            'summary_blocks' => [],
            'published_at' => now(),
            'last_updated_at' => now(),
        ]);
    }
}
