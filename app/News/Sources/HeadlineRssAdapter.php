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
use InvalidArgumentException;
use RuntimeException;
use SimpleXMLElement;
use Throwable;

abstract class HeadlineRssAdapter implements SourceAdapter
{
    protected const MAX_FEED_BYTES = 2_000_000;

    protected const MAX_ITEMS = 100;

    protected const FEED_URL = '';

    protected const SOURCE_SLUG = '';

    public function __construct(
        private readonly ArticleIngestor $ingestor,
        protected readonly ArticleUrlNormalizer $urls,
        private readonly PublicDnsResolver $dns,
    ) {}

    public function discover(Source $source): void
    {
        if ($source->slug !== static::SOURCE_SLUG || $source->feed_url !== static::FEED_URL) {
            throw new InvalidArgumentException('Invalid headline RSS source or feed URL.');
        }

        $response = Http::withHeaders(['User-Agent' => 'News/0.1 (RSS headline reader)'])
            ->withOptions(['allow_redirects' => false, ...$this->dns->options(static::FEED_URL), ...BoundedResponse::options(static::MAX_FEED_BYTES)])
            ->timeout(20)
            ->get(static::FEED_URL)
            ->throw();

        if (! str_contains(strtolower($response->header('Content-Type', '')), 'xml')) {
            throw new RuntimeException('The headline feed did not return XML.');
        }

        $previous = libxml_use_internal_errors(true);

        try {
            $feed = simplexml_load_string(BoundedResponse::body($response, static::MAX_FEED_BYTES), SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if ($feed === false || $feed->getName() !== 'rss' || ! isset($feed->channel)) {
            throw new RuntimeException('Invalid headline RSS feed.');
        }

        $count = 0;

        foreach ($feed->channel->item as $item) {
            if (++$count > static::MAX_ITEMS) {
                break;
            }

            $url = $this->canonicalUrl(trim((string) $item->link));
            $title = trim(strip_tags((string) $item->title));
            $date = trim((string) $item->pubDate);

            if ($url === null || strlen($url) > 255 || $title === '' || mb_strlen($title) > 255 || $date === '') {
                continue;
            }

            try {
                $publishedAt = Carbon::parse($date)->utc();
            } catch (Throwable) {
                continue;
            }

            $author = $this->author($item);

            $this->ingestor->storeHeadline($source, $url, $title, $publishedAt, $author);
        }
    }

    protected function author(SimpleXMLElement $item): ?string
    {
        $author = trim((string) $item->children('http://purl.org/dc/elements/1.1/')->creator)
            ?: trim((string) $item->author);

        return $author !== '' && mb_strlen($author) <= 255 ? $author : null;
    }

    abstract protected function canonicalUrl(string $rssUrl): ?string;
}
