<?php

namespace App\News\Deduplication;

final class ArticleUrlNormalizer
{
    public function normalize(string $url, bool $ignoreQuery = false): ?string
    {
        $parts = parse_url(trim($url));

        if (! is_array($parts)
            || strtolower($parts['scheme'] ?? '') !== 'https'
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['port'])) {
            return null;
        }

        $host = strtolower($parts['host']);
        $path = $parts['path'] ?? '/';

        if ($path === '' || $path[0] !== '/') {
            return null;
        }

        $path = rtrim($path, '/') ?: '/';
        $query = $ignoreQuery ? '' : $this->withoutTrackingParameters($parts['query'] ?? '');

        return 'https://'.$host.$path.($query === '' ? '' : '?'.$query);
    }

    private function withoutTrackingParameters(string $query): string
    {
        $parameters = array_filter(explode('&', $query), static function (string $parameter): bool {
            $key = strtolower(rawurldecode(explode('=', $parameter, 2)[0]));

            return ! str_starts_with($key, 'utm_')
                && ! in_array($key, ['fbclid', 'gclid', 'mc_cid', 'mc_eid'], true);
        });

        return implode('&', $parameters);
    }
}
