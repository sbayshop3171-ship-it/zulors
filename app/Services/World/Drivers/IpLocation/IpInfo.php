<?php

namespace App\Services\World\Drivers\IpLocation;

use Exception;
use App\Exceptions\World\IpLocationGettingFailedException;
use App\Services\World\Drivers\IpLocation\Interfaces\IpLocationDriverInterface;
use Illuminate\Support\Facades\Http;

class IpInfo implements IpLocationDriverInterface
{
	public function getLocation(string $ip): array
	{
		try {
			$apiToken = config('services.ipinfo.token');

			$response = Http::baseUrl('https://ipinfo.io/')->get($ip, [
				'token' => $apiToken
			]);

			if($response->successful()) {
				return $response->json();
			}

			else {
				throw new Exception('Failed to get location');
			}
		} 
		
		catch (Exception $e) {
			throw new IpLocationGettingFailedException($e->getMessage());
		}
	}
}