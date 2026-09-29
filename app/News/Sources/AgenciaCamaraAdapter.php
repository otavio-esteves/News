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

final class AgenciaCamaraAdapter implements SourceAdapter
{
    private const FEED_URL = 'https://www.camara.leg.br/noticias/rss/ultimas-noticias';

    private const MAX_FEED_BYTES = 2_000_000;

    public function __construct(
        private readonly ArticleIngestor $ingestor,
        private readonly ArticleUrlNormalizer $urls,
        private readonly PublicDnsResolver $dns,
    ) {}

    public function discover(Source $source): void
    {
        if ($source->feed_url !== self::FEED_URL) {
            throw new InvalidArgumentException('Invalid Agência Câmara feed URL.');
        }

        $response = Http::withHeaders(['User-Agent' => 'News/0.1 (RSS reader)'])
            ->withOptions(['allow_redirects' => false, ...$this->dns->options(self::FEED_URL), ...BoundedResponse::options(self::MAX_FEED_BYTES)])
            ->timeout(20)
            ->get(self::FEED_URL)
            ->throw();

        if (! str_contains(strtolower($response->header('Content-Type', '')), 'xml')) {
            throw new RuntimeException('The Agência Câmara feed did not return XML.');
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
            throw new RuntimeException('Invalid Agência Câmara RSS feed.');
        }

        foreach ($feed->channel->item as $item) {
            $this->persistItem($source, $item);
        }
    }

    private function persistItem(Source $source, SimpleXMLElement $item): void
    {
        $originalUrl = trim((string) $item->link);
        $canonicalUrl = $this->urls->normalize($originalUrl, ignoreQuery: true);
        $title = trim(strip_tags((string) $item->title));
        $date = trim((string) $item->pubDate);
        $html = (string) $item->children('http://purl.org/rss/1.0/modules/content/')->encoded;
        $content = $this->extractText($html);

        if ($canonicalUrl === null || strlen($originalUrl) > 255
            || ! preg_match('~^https://www\.camara\.leg\.br/noticias/\d+-[a-z0-9-]+$~', $canonicalUrl)
            || $title === '' || mb_strlen($title) > 255 || $date === '' || $content === '') {
            Log::warning('news.agencia_camara.item_skipped', ['url' => $originalUrl]);

            return;
        }

        try {
            $publishedAt = Carbon::parse($date)->utc();
        } catch (\Throwable) {
            Log::warning('news.agencia_camara.item_date_invalid', ['url' => $originalUrl]);

            return;
        }

        $this->ingestor->store($source, $originalUrl, $canonicalUrl, $title, $content, $publishedAt, 'Agência Câmara');
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

        foreach ($xpath->query('//p[not(ancestor::li)]|//li[not(ancestor::li)]') as $node) {
            $text = trim(preg_replace('/\s+/u', ' ', $node->textContent) ?? '');

            if ($text !== '') {
                $paragraphs[] = $text;
            }
        }

        return implode("\n\n", $paragraphs);
    }
}
