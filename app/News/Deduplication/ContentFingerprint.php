<?php

namespace App\News\Deduplication;

use Normalizer;

final class ContentFingerprint
{
    public function hash(string $content): string
    {
        $normalized = Normalizer::normalize($content, Normalizer::FORM_C) ?: $content;
        $normalized = preg_replace('/[\p{Z}\s]+/u', ' ', trim($normalized)) ?: trim($normalized);

        return hash('sha256', $normalized);
    }
}
