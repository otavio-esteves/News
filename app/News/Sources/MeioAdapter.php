<?php

namespace App\News\Sources;

final class MeioAdapter extends HeadlineRssAdapter
{
    protected const FEED_URL = 'https://www.canalmeio.com.br/feed/';

    protected const SOURCE_SLUG = 'meio';

    protected function canonicalUrl(string $rssUrl): ?string
    {
        $url = $this->urls->normalize($rssUrl, ignoreQuery: true);

        if ($url === null || ! preg_match('~^https://www\.canalmeio\.com\.br/\d{4}/\d{2}/\d{2}/[a-z0-9-]+$~', $url)) {
            return null;
        }

        return $url;
    }
}
