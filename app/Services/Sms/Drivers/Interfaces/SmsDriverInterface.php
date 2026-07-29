<?php

namespace App\Services\Sms\Drivers\Interfaces;

interface SmsDriverInterface
{
	public function send(string $to, string $message): bool;
}