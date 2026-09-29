<?php

namespace Database\Factories;

use App\Enums\StoryCategory;
use App\Models\Story;
use App\Models\StoryDraft;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StoryDraft> */
class StoryDraftFactory extends Factory
{
    protected $model = StoryDraft::class;

    public function definition(): array
    {
        return [
            'story_id' => Story::factory(),
            'title' => fake()->sentence(),
            'category' => fake()->randomElement(StoryCategory::cases()),
            'summary_blocks' => [],
            'generated_at' => now(),
        ];
    }
}
