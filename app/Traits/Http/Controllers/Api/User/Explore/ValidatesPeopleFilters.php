<?php

namespace App\Traits\Http\Controllers\Api\User\Explore;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

trait ValidatesPeopleFilters
{
	protected function getValidatedFilters(Request $request)
	{
		$filterRequest = $request->get('filter');
        $filterOptions = [];

        $validator = Validator::make(data: [
            'page' => data_get($filterRequest, 'page'),
            'query' => data_get($filterRequest, 'query')
        ], rules: [
            'page' => ['nullable', 'integer', 'min:1'],
            'query' => ['nullable', 'string', 'max:120']
        ]);
		
		if ($validator->passes()) {
            $filterOptions = $validator->validated();
        }

		return $filterOptions;
	}
}
