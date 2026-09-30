<?php

namespace App\News\Sources;

final class EstadaoAdapter extends HeadlineRssAdapter
{
    protected const FEED_URL = 'https://www.estadao.com.br/arc/outboundfeeds/rss/?outputType=xml';

    protected const SOURCE_SLUG = 'estadao';

    protected function canonicalUrl(string $rssUrl): ?string
    {
        $url = $this->urls->normalize($rssUrl, ignoreQuery: true);

        if ($url === null || ! preg_match('~^https://www\.estadao\.com\.br/[a-z0-9-]+(?:/[a-z0-9-]+)+$~', $url)) {
            return null;
        }

        return $url;
    }
}
