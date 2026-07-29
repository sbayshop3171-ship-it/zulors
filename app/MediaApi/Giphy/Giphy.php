<?php

namespace App\MediaApi\Giphy;

use App\Constants\Filesystem;
use Exception;
use GuzzleHttp\Client;

class Giphy
{
    public static function gifUrl(string $giphyId = ''): string
    {
        return "https://media2.giphy.com/media/{$giphyId}/giphy.gif";
    }

    public static function gifPreviewUrl(string $giphyId = ''): string
    {
        return "https://media2.giphy.com/media/{$giphyId}/giphy-preview.gif";
    }

    public static function validateGifId(string $gifId): bool
    {
        $apiKey = config('services.giphy.api_key');
        $url = "https://api.giphy.com/v1/gifs/{$gifId}?api_key={$apiKey}";

        $client = new Client();

        try {
            $response = $client->get($url);
            return $response->getStatusCode() === 200;
        } 
        
        catch (Exception $e) {
            return false;
        }
    }

    public static function getDisk(): string
    {
        return Filesystem::EXTERNAL_DISK_NAME;
    }
}