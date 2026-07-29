<?php

namespace App\Support\VO;

interface ValueObjectInterface
{
	public function equals(ValueObjectInterface $valueObject): bool;
}
