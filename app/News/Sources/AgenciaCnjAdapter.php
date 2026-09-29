<?php

namespace App\News\Sources;

use App\Models\Source;
use App\News\Contracts\SourceAdapter;
use App\News\Deduplication\ArticleIngestor;
use App\News\Deduplication\ArticleUrlNormalizer;
use App\News\Discovery\BoundedResponse;
use App\News\Discovery\PublicDnsResolver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

final class AgenciaCnjAdapter implements SourceAdapter
{
    private const FEED_URL = 'https://www.cnj.jus.br/wp-json/wp/v2/posts?categories=1415&per_page=10&_fields=id,date_gmt,link,title,content,categories';

    private const MAX_FEED_BYTES = 2_000_000;

    private const OWN_NEWS_CATEGORY = 1415;

    public function __construct(
        private readonly ArticleIngestor $ingestor,
        private readonly ArticleUrlNormalizer $urls,
        private readonly PublicDnsResolver $dns,
    ) {}

    public function discover(Source $source): void
    {
        if ($source->feed_url !== self::FEED_URL) {
            throw new InvalidArgumentException('Invalid Agência CNJ feed URL.');
        }

        $response = Http::withHeaders(['User-Agent' => 'News/0.1 (public news reader)'])
            ->withOptions(['allow_redirects' => false, ...$this->dns->options(self::FEED_URL), ...BoundedResponse::options(self::MAX_FEED_BYTES)])
            ->timeout(20)
            ->get(self::FEED_URL)
            ->throw();

        if (! str_contains(strtolower($response->header('Content-Type', '')), 'json')) {
            throw new RuntimeException('The Agência CNJ API did not return JSON.');
        }

        $items = json_decode(BoundedResponse::body($response, self::MAX_FEED_BYTES), true);

        if (! is_array($items) || ! array_is_list($items)) {
            throw new RuntimeException('Invalid Agência CNJ API response.');
        }

        foreach ($items as $item) {
            if (is_array($item)) {
                $this->persistItem($source, $item);
            }
        }
    }

    private function persistItem(Source $source, array $item): void
    {
        $categories = $item['categories'] ?? null;

        if (! is_array($categories) || ! in_array(self::OWN_NEWS_CATEGORY, $categories, true)) {
            return;
        }

        $originalUrl = $item['link'] ?? null;
        $rawTitle = $item['title']['rendered'] ?? null;
        $html = $item['content']['rendered'] ?? null;
        $date = $item['date_gmt'] ?? null;

        if (! is_string($originalUrl) || ! is_string($rawTitle) || ! is_string($html) || ! is_string($date)) {
            return;
        }

        $canonicalUrl = $this->urls->normalize($originalUrl, ignoreQuery: true);
        $title = trim(html_entity_decode(strip_tags($rawTitle), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $content = $this->extractText($html);

        if ($canonicalUrl === null || strlen($originalUrl) > 255
            || ! preg_match('~^https://www\.cnj\.jus\.br/[a-z0-9-]+$~', $canonicalUrl)
            || $title === '' || mb_strlen($title) > 255 || $date === '' || $content === '') {
            Log::warning('news.agencia_cnj.item_skipped', ['url' => $originalUrl]);

            return;
        }

        try {
            $publishedAt = Carbon::parse($date, 'UTC')->utc();
        } catch (\Throwable) {
            Log::warning('news.agencia_cnj.item_date_invalid', ['url' => $originalUrl]);

            return;
        }

        $this->ingestor->store($source, $originalUrl, $canonicalUrl, $title, $content, $publishedAt, 'Agência CNJ de Notícias');
    }

    private function extractText(string $html): string
    {
        $document = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);

        try {
            $document->loadHTML('<?xml encoding="utf-8"?><body>'.$html.'</body>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $xpath = new \DOMXPath($document);

        foreach ($xpath->query('//script|//style|//noscript|//figure|//figcaption|//iframe') as $node) {
            $node->parentNode?->removeChild($node);
        }

        $paragraphs = [];

        foreach ($xpath->query('//p[not(ancestor::p)]|//li[not(ancestor::li)]') as $node) {
            $text = trim(preg_replace('/\s+/u', ' ', $node->textContent) ?? '');

            if ($text !== '') {
                $paragraphs[] = $text;
            }
        }

        return implode("\n\n", $paragraphs);
    }
}
