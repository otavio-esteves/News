<?php

namespace App\News\Categorization;

use App\Enums\StoryCategory;

final class ArticleCategoryClassifier
{
    public function classify(string $sourceSlug, string $canonicalUrl): ?StoryCategory
    {
        if (in_array($sourceSlug, ['agencia-senado', 'agencia-camara'], true)) {
            return StoryCategory::Politica;
        }

        if (! in_array($sourceSlug, ['agencia-brasil', 'radioagencia-nacional'], true)) {
            return null;
        }

        $segments = explode('/', trim(parse_url($canonicalUrl, PHP_URL_PATH) ?: '', '/'));
        $section = $sourceSlug === 'radioagencia-nacional' ? ($segments[1] ?? '') : $segments[0];

        return match ($section) {
            'politica' => StoryCategory::Politica,
            'internacional' => StoryCategory::Mundo,
            'economia' => StoryCategory::Economia,
            'tecnologia' => StoryCategory::Tecnologia,
            'cultura' => StoryCategory::Cultura,
            'entretenimento' => StoryCategory::Entretenimento,
            'geral', 'educacao', 'saude', 'esportes', 'meio-ambiente', 'direitos-humanos', 'justica' => StoryCategory::Brasil,
            default => null,
        };
    }
}
