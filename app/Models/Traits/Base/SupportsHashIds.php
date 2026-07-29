<?php

namespace App\Models\Traits\Base;

trait SupportsHashIds
{
	public function getHashIdAttribute(): string
	{
		return encode_id($this->id);
	}

	public function scopeWhereHashId($query, string $hashId)
	{
		return $query->where('id', decode_id($hashId));
	}

	public function scopeFindByHashId($query, string $hashId)
	{
		return $query->whereHashId($hashId)->first();
	}
}
