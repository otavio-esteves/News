<?php

namespace Database\Factories;

use App\Models\Source;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Source> */
class SourceFactory extends Factory
{
    protected $model = Source::class;

    public function definition(): array
    {
        $slug = fake()->unique()->slug();

        return [
            'slug' => $slug,
            'name' => fake()->company(),
            'homepage_url' => "https://example.org/{$slug}",
            'enabled' => false,
            'fetch_interval_minutes' => 15,
            'config' => [],
        ];
    }
}
