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

final class AgenciaBrasilAdapter implements SourceAdapter
{
    private const MAX_FEED_BYTES = 2_000_000;

    public function __construct(
        private readonly ArticleIngestor $ingestor,
        private readonly ArticleUrlNormalizer $urls,
        private readonly PublicDnsResolver $dns,
    ) {}

    public function discover(Source $source): void
    {
        $feedUrl = $source->feed_url;

        if (! is_string($feedUrl) || ! preg_match('~^https://agenciabrasil\.ebc\.com\.br/rss/[a-z]+/feed\.xml$~', $feedUrl)) {
            throw new InvalidArgumentException('Invalid Agência Brasil feed URL.');
        }

        $response = Http::withHeaders(['User-Agent' => 'News/0.1 (RSS reader)'])
            ->withOptions(['allow_redirects' => false, ...$this->dns->options($feedUrl), ...BoundedResponse::options(self::MAX_FEED_BYTES)])
            ->timeout(20)
            ->get($feedUrl)
            ->throw();

        if (! str_contains(strtolower($response->header('Content-Type', '')), 'xml')) {
            throw new RuntimeException('The Agência Brasil feed did not return XML.');
        }

        $xml = BoundedResponse::body($response, self::MAX_FEED_BYTES);

        $previous = libxml_use_internal_errors(true);

        try {
            $feed = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if ($feed === false || $feed->getName() !== 'rss' || ! isset($feed->channel)) {
            throw new RuntimeException('Invalid Agência Brasil RSS feed.');
        }

        foreach ($feed->channel->item as $item) {
            $this->persistItem($source, $item);
        }
    }

    private function persistItem(Source $source, SimpleXMLElement $item): void
    {
        $creator = trim((string) $item->children('http://purl.org/dc/elements/1.1/')->creator);

        if (! str_contains(mb_strtolower($creator), 'agência brasil')) {
            return;
        }

        $originalUrl = trim((string) $item->link);
        $url = $this->canonicalUrl($originalUrl);
        $title = trim(strip_tags((string) $item->title));
        $date = trim((string) $item->pubDate);
        $content = $this->extractText((string) $item->description);

        if ($url === null || $title === '' || mb_strlen($title) > 255 || strlen($originalUrl) > 255 || $date === '' || $content === '') {
            Log::warning('news.agencia_brasil.item_skipped', ['url' => $originalUrl]);

            return;
        }

        try {
            $publishedAt = Carbon::parse($date)->utc();
        } catch (\Throwable) {
            Log::warning('news.agencia_brasil.item_date_invalid', ['url' => $originalUrl]);

            return;
        }

        $this->ingestor->store($source, $originalUrl, $url, $title, $content, $publishedAt, $creator);
    }

    private function canonicalUrl(string $url): ?string
    {
        $canonical = $this->urls->normalize($url, ignoreQuery: true);
        $parts = $canonical === null ? false : parse_url($canonical);

        if (! is_array($parts)
            || ($parts['host'] ?? null) !== 'agenciabrasil.ebc.com.br'
            || ! preg_match('~^/[a-z-]+/noticia/\d{4}-\d{2}/[a-z0-9-]+$~', $parts['path'] ?? '')) {
            return null;
        }

        return $canonical;
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
