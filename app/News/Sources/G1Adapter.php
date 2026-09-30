<?php

namespace App\News\Sources;

final class G1Adapter extends HeadlineRssAdapter
{
    protected const FEED_URL = 'https://g1.globo.com/rss/g1/';

    protected const SOURCE_SLUG = 'g1';

    protected function canonicalUrl(string $rssUrl): ?string
    {
        $url = $this->urls->normalize($rssUrl, ignoreQuery: true);

        if ($url === null || ! preg_match('~^https://g1\.globo\.com/(?:[a-z0-9-]+/)+noticia/\d{4}/\d{2}/\d{2}/[a-z0-9-]+\.ghtml$~', $url)) {
            return null;
        }

        return $url;
    }
}
