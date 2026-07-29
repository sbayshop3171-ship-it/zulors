<?php

namespace App\Services\World\Drivers\IpLocation\Interfaces;

interface IpLocationDriverInterface
{
	public function getLocation(string $ip): array;
}