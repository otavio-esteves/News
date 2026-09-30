<?php

namespace App\News\Sources;

final class FolhaAdapter extends HeadlineRssAdapter
{
    protected const FEED_URL = 'https://feeds.folha.uol.com.br/emcimadahora/rss091.xml';

    protected const SOURCE_SLUG = 'folha';

    protected function canonicalUrl(string $rssUrl): ?string
    {
        $prefix = 'https://redir.folha.com.br/redir/online/emcimadahora/rss091/*';

        if (! str_starts_with($rssUrl, $prefix)) {
            return null;
        }

        $url = $this->urls->normalize(substr($rssUrl, strlen($prefix)), ignoreQuery: true);

        if ($url === null || ! preg_match('~^https://(?:www1|f5)\.folha\.uol\.com\.br/(?:[a-z0-9-]+/)+\d{4}/\d{2}/[a-z0-9-]+\.shtml$~', $url)) {
            return null;
        }

        return $url;
    }
}
