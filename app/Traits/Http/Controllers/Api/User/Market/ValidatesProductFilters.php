<?php

namespace App\Traits\Http\Controllers\Api\User\Market;

use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;

trait ValidatesProductFilters
{
	protected function getValidatedFilters(Request $request)
	{
		$filterRequest = $request->get('filter');
        $filterOptions = [];

        $validator = Validator::make(data: [
            'cursor' => data_get($filterRequest, 'cursor'),
            'query' => data_get($filterRequest, 'query'),
            'category_id' => data_get($filterRequest, 'category_id'),
            'price' => data_get($filterRequest, 'price'),
            'is_store' => data_get($filterRequest, 'is_store', false),
            'with_discount' => data_get($filterRequest, 'with_discount', false),
            'high_rating' => data_get($filterRequest, 'high_rating', false),
            'currencies' => data_get($filterRequest, 'currencies'),
            'conditions' => data_get($filterRequest, 'conditions'),
            'types' => data_get($filterRequest, 'types'),
        ], rules: [
            'cursor' => ['nullable', 'integer', 'min:1'],
            'query' => ['nullable', 'string', 'max:120'],
            'category_id' => ['nullable', 'integer', 'min:1'],
            'price.from' => ['nullable', 'numeric', 'min:1'],
            'price.to' => ['nullable', 'numeric', 'min:1'],
            'is_store' => ['nullable', 'boolean'],
            'with_discount' => ['nullable', 'boolean'],
            'high_rating' => ['nullable', 'boolean'],
            'currencies' => ['nullable', 'array'],
            'currencies.*' => ['boolean'],
            'conditions' => ['nullable', 'array'],
            'conditions.*' => ['boolean'],
            'types' => ['nullable', 'array'],
            'types.*' => ['boolean'],
        ]);

        if ($validator->passes()) {
            $filterOptions = $validator->validated();
        }

		return $filterOptions;
	}
}
