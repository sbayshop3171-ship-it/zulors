<?php

namespace App\Services\Sms\Drivers;

use Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Http;
use App\Exceptions\Sms\SmsSendingFailedException;
use App\Services\Sms\Drivers\Interfaces\SmsDriverInterface;

class SmsaeroSmsSender implements SmsDriverInterface {
	private $smsClient;
	private $endpoint = 'https://gate.smsaero.ru/v2/sms/send';

	public function __construct() {
		$this->smsClient = Http::withBasicAuth(config('services.smsaero.login'), config('services.smsaero.api_key'))->withHeaders([
			'Content-Type' => 'application/json',
		]);
	}

	public function send(string $receiptNumber, string $smsMessage): bool
	{
		try {
			$response = $this->smsClient->post($this->endpoint, [
				'number' => $receiptNumber,
				'text' => $smsMessage,
				'sign' => config('services.smsaero.sender_name'),
				'channel' => config('services.smsaero.channel'),
			]);
	
			$responseData = $response->json();
	
			if ($response->successful()) {
				return isset($responseData['success']) && $responseData['success'] === true;
			}
		} catch (Exception $e) {
			throw new SmsSendingFailedException($e->getMessage());
		}
	}
}