<?php

use App\Models\Article;
use App\Models\Source;
use App\News\Deduplication\ArticleIngestor;
use App\News\Deduplication\ArticleUrlNormalizer;
use App\News\Deduplication\ContentFingerprint;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('normalizes URL variants while preserving meaningful query parameters', function () {
    $urls = new ArticleUrlNormalizer;

    expect($urls->normalize('HTTPS://Example.org/news/item/?utm_source=rss&edition=morning&fbclid=abc#top'))
        ->toBe('https://example.org/news/item?edition=morning')
        ->and($urls->normalize('https://example.org/news/item?edition=morning', ignoreQuery: true))
        ->toBe('https://example.org/news/item')
        ->and($urls->normalize('https://user@example.org/news/item'))->toBeNull();
});

it('fingerprints equivalent whitespace and Unicode text identically', function () {
    $fingerprint = new ContentFingerprint;

    expect($fingerprint->hash("Café\n\ncom notícia"))
        ->toBe($fingerprint->hash("Cafe\u{0301}   com notícia"));
});

it('deduplicates repeated content within a source but keeps distinct source provenance', function () {
    $firstSource = Source::factory()->create();
    $secondSource = Source::factory()->create();
    $ingestor = app(ArticleIngestor::class);
    $publishedAt = now();

    $first = $ingestor->store(
        $firstSource,
        'https://example.org/one?utm_source=feed',
        'https://example.org/one',
        'Original title',
        "Uma notícia com conteúdo.\n\nSegundo parágrafo.",
        $publishedAt,
    );
    $sameContent = $ingestor->store(
        $firstSource,
        'https://example.org/another-path',
        'https://example.org/another-path',
        'Another title',
        'Uma notícia com conteúdo. Segundo parágrafo.',
        $publishedAt,
    );
    $sameUrl = $ingestor->store(
        $firstSource,
        'https://example.org/one',
        'https://example.org/one',
        'Changed title',
        'Changed content.',
        $publishedAt,
    );
    $otherSource = $ingestor->store(
        $secondSource,
        'https://another.example.org/one',
        'https://another.example.org/one',
        'Another publisher',
        'Uma notícia com conteúdo. Segundo parágrafo.',
        $publishedAt,
    );

    expect($sameContent->id)->toBe($first->id)
        ->and($sameUrl->id)->toBe($first->id)
        ->and($otherSource->id)->not->toBe($first->id)
        ->and(Article::count())->toBe(2)
        ->and($first->fresh()->title)->toBe('Original title');
});
