<?php

namespace App\News\Sources;

use SimpleXMLElement;

final class CartaCapitalAdapter extends HeadlineRssAdapter
{
    protected const FEED_URL = 'https://www.cartacapital.com.br/feed/';

    protected const SOURCE_SLUG = 'cartacapital';

    protected function acceptsItem(SimpleXMLElement $item): bool
    {
        return trim((string) $item->children('http://purl.org/dc/elements/1.1/')->creator) === 'CartaCapital';
    }

    protected function canonicalUrl(string $rssUrl): ?string
    {
        $url = $this->urls->normalize($rssUrl, ignoreQuery: true);

        if ($url === null || ! preg_match('~^https://www\.cartacapital\.com\.br/[a-z0-9-]+(?:/[a-z0-9-]+)+$~', $url)) {
            return null;
        }

        return $url;
    }
}
