<?php

namespace App\News\Matching;

use App\Models\Story;

final readonly class StoryCandidate
{
    public function __construct(
        public Story $story,
        public float $score,
    ) {}
}
