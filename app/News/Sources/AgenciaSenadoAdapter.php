<?php

namespace App\News\Sources;

use App\Models\Article;
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
use Throwable;

final class AgenciaSenadoAdapter implements SourceAdapter
{
    private const FEED_URL = 'https://www12.senado.leg.br/noticias/rss.xml';

    private const MAX_ARTICLES_PER_RUN = 3;

    public function __construct(
        private readonly ArticleIngestor $ingestor,
        private readonly ArticleUrlNormalizer $urls,
        private readonly PublicDnsResolver $dns,
    ) {}

    public function discover(Source $source): void
    {
        if ($source->feed_url !== self::FEED_URL) {
            throw new InvalidArgumentException('Invalid Agência Senado feed URL.');
        }

        $xml = $this->download(self::FEED_URL, 'xml', 200_000);
        $previous = libxml_use_internal_errors(true);

        try {
            $feed = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if ($feed === false || $feed->getName() !== 'rss' || ! isset($feed->channel)) {
            throw new RuntimeException('Invalid Agência Senado RSS feed.');
        }

        $attempted = 0;
        $firstFailure = null;

        foreach ($feed->channel->item as $item) {
            $originalUrl = trim((string) $item->guid);
            $canonicalUrl = $this->canonicalUrl($originalUrl);

            if ($canonicalUrl === null || Article::where('canonical_url', $canonicalUrl)->exists()) {
                continue;
            }

            if ($attempted >= self::MAX_ARTICLES_PER_RUN) {
                break;
            }

            $published = trim((string) $item->pubDate);

            if ($published === '') {
                continue;
            }

            try {
                $publishedAt = Carbon::parse($published)->utc();
            } catch (Throwable) {
                Log::warning('news.agencia_senado.item_date_invalid', ['url' => $originalUrl]);

                continue;
            }

            $attempted++;
            try {
                $html = $this->download($canonicalUrl, 'text/html', 1_000_000);
                [$title, $content, $author] = $this->extractArticle($html);

                if ($title === '' || mb_strlen($title) > 255 || $content === '') {
                    Log::warning('news.agencia_senado.item_skipped', ['url' => $originalUrl]);

                    continue;
                }

                $this->ingestor->store($source, $originalUrl, $canonicalUrl, $title, $content, $publishedAt, $author);
            } catch (Throwable $exception) {
                $firstFailure ??= $exception;
                Log::warning('news.agencia_senado.item_failed', [
                    'url' => $originalUrl,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        if ($firstFailure !== null) {
            throw $firstFailure;
        }
    }

    private function canonicalUrl(string $url): ?string
    {
        $canonical = $this->urls->normalize($url, ignoreQuery: true);

        if ($canonical === null || strlen($url) > 255
            || ! preg_match('~^https://www12\.senado\.leg\.br/noticias/materias/\d{4}/\d{2}/\d{2}/[a-z0-9-]+$~', $canonical)) {
            return null;
        }

        return $canonical;
    }

    private function download(string $url, string $contentType, int $maxBytes): string
    {
        $response = Http::withHeaders(['User-Agent' => 'News/0.1 (RSS reader)'])
            ->withOptions(['allow_redirects' => false, ...$this->dns->options($url), ...BoundedResponse::options($maxBytes)])
            ->timeout(10)
            ->get($url)
            ->throw();

        if (! str_contains(strtolower($response->header('Content-Type', '')), $contentType)) {
            throw new RuntimeException('Unexpected Agência Senado response type.');
        }

        return BoundedResponse::body($response, $maxBytes);
    }

    private function extractArticle(string $html): array
    {
        $document = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);

        try {
            $document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $xpath = new \DOMXPath($document);
        $title = trim($xpath->query('//*[@id="materia"]/h1')->item(0)?->textContent ?? '');
        $body = $xpath->query('//*[@id="textoMateria"]')->item(0);

        if ($body === null) {
            return [$title, '', null];
        }

        $paragraphs = [];

        foreach ($xpath->query('.//p[not(@class="text-muted")]|.//li', $body) as $node) {
            $text = trim(preg_replace('/\s+/u', ' ', $node->textContent) ?? '');

            if ($text !== '' && ! str_contains($text, 'Reprodução autorizada mediante citação')) {
                $paragraphs[] = $text;
            }
        }

        $byline = trim($xpath->query('//*[@id="materia"]/p/small')->item(0)?->textContent ?? '');
        $author = trim(explode('|', $byline, 2)[0]);

        return [$title, implode("\n\n", $paragraphs), $author !== '' ? $author : 'Agência Senado'];
    }
}
