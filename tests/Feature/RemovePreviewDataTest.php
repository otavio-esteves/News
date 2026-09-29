<?php

use App\Models\Article;
use App\Models\Source;
use App\Models\Story;
use Database\Seeders\AgenciaBrasilSourceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Fixtures\PreviewStoriesSeeder;

uses(RefreshDatabase::class);

it('removes only fictional preview records', function () {
    $this->seed(PreviewStoriesSeeder::class);
    $this->seed(AgenciaBrasilSourceSeeder::class);
    $realSource = Source::where('slug', 'agencia-brasil')->firstOrFail();
    Article::factory()->create(['source_id' => $realSource->id]);

    expect(Artisan::call('news:remove-preview'))->toBe(0)
        ->and(Artisan::call('news:remove-preview'))->toBe(0)
        ->and(Source::count())->toBe(1)
        ->and(Article::count())->toBe(1)
        ->and(Story::count())->toBe(0);
});
