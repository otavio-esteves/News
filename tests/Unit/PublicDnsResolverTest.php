<?php

use App\News\Discovery\PublicDnsResolver;

it('pins source requests to a validated public address', function () {
    $resolver = new class extends PublicDnsResolver
    {
        protected function lookup(string $host): array
        {
            return [['ip' => '93.184.215.14']];
        }
    };

    expect($resolver->options('https://agenciabrasil.ebc.com.br/feed.xml'))
        ->toBe([
            'proxy' => '',
            'curl' => [CURLOPT_RESOLVE => ['agenciabrasil.ebc.com.br:443:93.184.215.14']],
        ]);
});

it('rejects a host if any resolved address is private', function () {
    $resolver = new class extends PublicDnsResolver
    {
        protected function lookup(string $host): array
        {
            return [['ip' => '93.184.215.14'], ['ip' => '127.0.0.1']];
        }
    };

    expect(fn () => $resolver->options('https://www12.senado.leg.br/noticias/rss.xml'))
        ->toThrow(RuntimeException::class);
});
