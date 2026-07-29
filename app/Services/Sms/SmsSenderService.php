<?php

namespace App\Services\Sms;

use InvalidArgumentException;
use App\Services\Sms\Drivers\LogSmsSender;
use App\Services\Sms\Drivers\VonageSmsSender;
use App\Services\Sms\Drivers\SmsaeroSmsSender;
use App\Exceptions\Sms\SmsSendingFailedException;
use App\Services\Sms\Drivers\Interfaces\SmsDriverInterface;

class SmsSenderService
{
	protected SmsDriverInterface $driver;
	private string $receiptNumber;
	private string $smsMessage;
	
	public function __construct() {
		$this->setDriver(config('sms.driver'));
	}

	public function send() {
		try {
			return $this->driver->send($this->receiptNumber, $this->smsMessage);
		}
		
		catch (SmsSendingFailedException $e) {
			return false;
		}
	}

	public function setNumber(string $number) {
		$this->receiptNumber = $number;
	}

	public function setMessage(string $message) {
		$this->smsMessage = $message;
	}

	public function setDriver(string $driver) {
		$drivers = [
			'vonage' => VonageSmsSender::class,
			'smsaero' => SmsaeroSmsSender::class,
			'log' => LogSmsSender::class
		];

		if (isset($drivers[$driver])) {
			$this->driver = new $drivers[$driver];
		}

		else{
			throw new InvalidArgumentException('Invalid SMS driver');
		}
	}
}