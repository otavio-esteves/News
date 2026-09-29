<?php

namespace App\News\Discovery;

use Illuminate\Http\Client\Response as LaravelResponse;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

final class BoundedResponse
{
    public static function options(int $maxBytes): array
    {
        return [
            'on_headers' => static function (ResponseInterface $response) use ($maxBytes): void {
                $length = $response->getHeaderLine('Content-Length');

                if (ctype_digit($length) && (float) $length > $maxBytes) {
                    throw new RuntimeException('Source response exceeds the size limit.');
                }
            },
            'progress' => static function (int $expected, int $downloaded) use ($maxBytes): void {
                if ($downloaded > $maxBytes) {
                    throw new RuntimeException('Source response exceeds the size limit.');
                }
            },
        ];
    }

    public static function body(LaravelResponse $response, int $maxBytes): string
    {
        $body = $response->body();

        if (strlen($body) > $maxBytes) {
            throw new RuntimeException('Source response exceeds the size limit.');
        }

        return $body;
    }
}
