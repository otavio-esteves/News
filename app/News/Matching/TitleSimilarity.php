<?php

namespace App\News\Matching;

use Illuminate\Support\Str;

final class TitleSimilarity
{
    private const STOP_WORDS = [
        'a', 'o', 'as', 'os', 'um', 'uma', 'de', 'da', 'do', 'das', 'dos',
        'e', 'em', 'no', 'na', 'nos', 'nas', 'para', 'por', 'com', 'ao',
        'aos', 'que', 'foi', 'ser', 'pelo', 'pela', 'pelos', 'pelas',
    ];

    public function score(string $first, string $second): float
    {
        $left = $this->tokens($first);
        $right = $this->tokens($second);

        if (count($left) < 4 || count($right) < 4) {
            return 0.0;
        }

        $shared = count(array_intersect($left, $right));

        if ($shared < 3) {
            return 0.0;
        }

        return $shared / count(array_unique([...$left, ...$right]));
    }

    private function tokens(string $title): array
    {
        $normalized = mb_strtolower(Str::ascii($title));
        $words = preg_split('/[^a-z0-9]+/', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_filter(
            $words,
            fn (string $word): bool => (strlen($word) >= 3 || ctype_digit($word))
                && ! in_array($word, self::STOP_WORDS, true),
        )));
    }
}
