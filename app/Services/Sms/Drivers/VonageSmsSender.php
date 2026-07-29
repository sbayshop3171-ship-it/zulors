<?php

namespace App\Services\Sms\Drivers;

use Exception;
use Vonage\Client;
use Vonage\SMS\Message\SMS;
use Vonage\Client\Credentials\Basic;
use App\Exceptions\Sms\SmsSendingFailedException;
use App\Services\Sms\Drivers\Interfaces\SmsDriverInterface;

class VonageSmsSender implements SmsDriverInterface
{
	private $smsClient;

	public function __construct()
	{
		$basic = new Basic(config('services.vonage.api_key'), config('services.vonage.api_secret'));
		$this->smsClient = new Client($basic);
	}

	public function send(string $receiptNumber, string $smsMessage): bool
	{
		try {
			$messageInstance = new SMS($receiptNumber, 'MyBrand', $smsMessage, 'unicode');
			$messageInstance->setClientRef('test-message');
			$response = $this->smsClient->sms()->send($messageInstance);
			
			$message = $response->current();

			return $message->getStatus() === '0';
		} catch (Exception $e) {
			throw new SmsSendingFailedException($e->getMessage());
		}
	}
}