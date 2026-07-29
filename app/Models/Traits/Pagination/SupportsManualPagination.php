<?php

namespace App\Models\Traits\Pagination;

trait SupportsManualPagination
{
	public function scopeSimplePaginateManual($query, int $perPage = 15, int $page = 1)
	{
		return $query->simplePaginate($perPage, ['*'], 'page', $page);
	}
}
