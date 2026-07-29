<?php

namespace App\Services\Sms\Drivers;

use Illuminate\Support\Facades\Log;
use App\Services\Sms\Drivers\Interfaces\SmsDriverInterface;

class LogSmsSender implements SmsDriverInterface {
	public function send(string $receiptNumber, string $smsMessage): bool {

		// TODO: implement
		Log::info("Sending SMS to $receiptNumber: $smsMessage");

		return true;
	}
}