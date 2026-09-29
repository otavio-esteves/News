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
use SimpleXMLElement;

final class RadioagenciaNacionalAdapter implements SourceAdapter
{
    private const FEED_URL = 'https://agenciabrasil.ebc.com.br/radioagencia-nacional/rss/ultimasnoticias/feed.xml';

    private const MAX_FEED_BYTES = 2_000_000;

    public function __construct(
        private readonly ArticleIngestor $ingestor,
        private readonly ArticleUrlNormalizer $urls,
        private readonly PublicDnsResolver $dns,
    ) {}

    public function discover(Source $source): void
    {
        if ($source->feed_url !== self::FEED_URL) {
            throw new InvalidArgumentException('Invalid Radioagência Nacional feed URL.');
        }

        $response = Http::withHeaders(['User-Agent' => 'News/0.1 (RSS reader)'])
            ->withOptions(['allow_redirects' => false, ...$this->dns->options(self::FEED_URL), ...BoundedResponse::options(self::MAX_FEED_BYTES)])
            ->timeout(20)
            ->get(self::FEED_URL)
            ->throw();

        if (! str_contains(strtolower($response->header('Content-Type', '')), 'xml')) {
            throw new RuntimeException('The Radioagência Nacional feed did not return XML.');
        }

        $previous = libxml_use_internal_errors(true);

        try {
            $feed = simplexml_load_string(BoundedResponse::body($response, self::MAX_FEED_BYTES), SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if ($feed === false || $feed->getName() !== 'rss' || ! isset($feed->channel)) {
            throw new RuntimeException('Invalid Radioagência Nacional RSS feed.');
        }

        foreach ($feed->channel->item as $item) {
            $this->persistItem($source, $item);
        }
    }

    private function persistItem(Source $source, SimpleXMLElement $item): void
    {
        $author = trim((string) $item->children('http://purl.org/dc/elements/1.1/')->creator);

        if (! preg_match('/r[aá]dio nacional/iu', $author)) {
            return;
        }

        $originalUrl = trim((string) $item->link);
        $canonicalUrl = $this->urls->normalize($originalUrl, ignoreQuery: true);
        $title = trim(strip_tags((string) $item->title));
        $date = trim((string) $item->pubDate);
        $content = $this->extractText((string) $item->description);

        if ($canonicalUrl === null || strlen($originalUrl) > 255
            || ! preg_match('~^https://agenciabrasil\.ebc\.com\.br/radioagencia-nacional/[a-z-]+/audio/\d{4}-\d{2}/[a-z0-9-]+$~', $canonicalUrl)
            || $title === '' || mb_strlen($title) > 255 || $date === '' || $content === '') {
            Log::warning('news.radioagencia_nacional.item_skipped', ['url' => $originalUrl]);

            return;
        }

        try {
            $publishedAt = Carbon::parse($date)->utc();
        } catch (\Throwable) {
            Log::warning('news.radioagencia_nacional.item_date_invalid', ['url' => $originalUrl]);

            return;
        }

        $this->ingestor->store($source, $originalUrl, $canonicalUrl, $title, $content, $publishedAt, $author);
    }

    private function extractText(string $html): string
    {
        if ($html === '') {
            return '';
        }

        $document = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);

        try {
            $document->loadHTML('<?xml encoding="utf-8"?><body>'.$html.'</body>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $xpath = new \DOMXPath($document);

        foreach ($xpath->query('//script|//style|//noscript|//figure|//figcaption') as $node) {
            $node->parentNode?->removeChild($node);
        }

        $paragraphs = [];

        foreach ($xpath->query('//p[not(ancestor::p)]') as $node) {
            $text = trim(preg_replace('/\s+/u', ' ', $node->textContent) ?? '');

            if ($text !== '') {
                $paragraphs[] = $text;
            }
        }

        return implode("\n\n", $paragraphs);
    }
}
