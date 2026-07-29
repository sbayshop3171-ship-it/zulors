<?php

namespace App\Services\World;

use InvalidArgumentException;
use App\Services\World\Drivers\IpLocation\IpInfo;
use App\Exceptions\World\IpLocationGettingFailedException;
use App\Services\World\Drivers\IpLocation\Interfaces\IpLocationDriverInterface;

class IpLocationService
{
	protected IpLocationDriverInterface $driver;

	public function getLocation(string $ip) {
		try {
			return $this->driver->getLocation($ip);
		}
		
		catch (IpLocationGettingFailedException $e) {
			return false;
		}
	}

	public function __construct() {
		$this->setDriver(config('world.ip_geoloaction.driver'));
	}

	public function setDriver(string $driver) {
		$drivers = [
			'ipinfo' => IpInfo::class
		];

		if (isset($drivers[$driver])) {
			$this->driver = new $drivers[$driver];
		}

		else{
			throw new InvalidArgumentException('Invalid IP location driver');
		}
	}
}