<?php

namespace Database\Factories;

use App\Enums\StoryCategory;
use App\Models\Story;
use App\Models\StoryRevision;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StoryRevision> */
class StoryRevisionFactory extends Factory
{
    protected $model = StoryRevision::class;

    public function definition(): array
    {
        return [
            'story_id' => Story::factory()->published(),
            'version' => 1,
            'title' => fake()->sentence(),
            'category' => fake()->randomElement(StoryCategory::cases()),
            'summary_blocks' => [],
            'reference_snapshots' => [],
            'published_at' => now(),
        ];
    }
}
