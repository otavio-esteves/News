<?php

namespace Database\Factories;

use App\Models\AiRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AiRun> */
class AiRunFactory extends Factory
{
    protected $model = AiRun::class;

    public function definition(): array
    {
        return [
            'type' => 'story_writer',
            'provider' => 'openai',
            'model' => 'example-model',
            'prompt_version' => 'v1',
            'schema_version' => 'v1',
            'input_hash' => hash('sha256', fake()->unique()->uuid()),
            'status' => 'completed',
            'started_at' => now(),
        ];
    }
}
