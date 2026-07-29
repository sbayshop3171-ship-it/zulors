<?php

namespace App\Traits\Http\Controllers\Api\User\Jobs;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

trait ValidatesJobFilters
{
	protected function getValidatedFilters(Request $request)
	{
		$filterRequest = $request->get('filter');
        $filterOptions = [];

        $validator = Validator::make(data: [
            'cursor' => data_get($filterRequest, 'cursor'),
            'query' => data_get($filterRequest, 'query'),
            'category_id' => data_get($filterRequest, 'category_id')
        ], rules: [
            'cursor' => ['nullable', 'integer', 'min:1'],
            'query' => ['nullable', 'string', 'max:120'],
            'category_id' => ['nullable', 'integer', 'min:1']
        ]);

        if ($validator->passes()) {
            $filterOptions = $validator->validated();
        }

		return $filterOptions;
	}
}
