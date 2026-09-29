<?php

namespace App\News\Discovery;

use RuntimeException;

class PublicDnsResolver
{
    public function options(string $url): array
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            throw new RuntimeException('Cannot resolve a URL without a host.');
        }

        $records = $this->lookup($host);
        $addresses = [];

        foreach ($records as $record) {
            $address = $record['ip'] ?? $record['ipv6'] ?? null;

            if (! is_string($address) || ! filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new RuntimeException("The source host [{$host}] resolved to an unsafe address.");
            }

            $addresses[] = str_contains($address, ':') ? "[{$address}]" : $address;
        }

        if ($addresses === []) {
            throw new RuntimeException("The source host [{$host}] has no public IP address.");
        }

        return [
            'proxy' => '',
            'curl' => [CURLOPT_RESOLVE => ["{$host}:443:".implode(',', $addresses)]],
        ];
    }

    protected function lookup(string $host): array
    {
        return dns_get_record($host, DNS_A | DNS_AAAA) ?: [];
    }
}
