<?php

namespace App\Services\Ad;

use InvalidArgumentException;

class AdUrlNormalizer
{
    public function normalize(?string $url): ?string
    {
        $url = trim((string) $url);

        if($url === '') {
            return null;
        }

        if(! filter_var($url, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('Enter a valid destination URL.');
        }

        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = (string) ($parts['host'] ?? '');

        if(! in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new InvalidArgumentException('Destination URL must use http or https.');
        }

        return $url;
    }
}