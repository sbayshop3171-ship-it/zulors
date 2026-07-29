<?php

namespace App\Traits\Http\Controllers\Api\User\Timeline;

use App\Database\Configs\Table;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;

trait ValidatesPollData
{
    private function validatePollData(array $data)
    {
        $validator = Validator::make($data, [
            'poll_options' => ['required', 'array', 'min:2', 'max:10'],
            'poll_options.*.choice_text' => ['required', 'string', 'min:1', 'max:40'],
        ], messages: [
            'poll_options.required' => __('post.validation.poll.options_required'),
            'poll_options.array' => __('post.validation.poll.invalid_data'),
            'poll_options.min' => __('post.validation.poll.min_options'),
            'poll_options.max' => __('post.validation.poll.min_options'),
            'poll_options.*.choice_text.required' => __('post.validation.poll.option_text'),
            'poll_options.*.choice_text.string' => __('post.validation.poll.invalid_data'),
            'poll_options.*.choice_text.min' => __('post.validation.poll.option_text_min'),
            'poll_options.*.choice_text.max' => __('post.validation.poll.option_text_max'),
        ]);
    
        if ($validator->fails()) {
            $this->throwValidationError($validator);
        }
    }

    private function validatePollVote(array $data)
    {
        $validator = Validator::make($data, [
            'poll_id' => ['required', 'integer', 'min:1', Rule::exists(Table::POST_POLLS, 'id')],
            'choice_id' => ['required', 'integer', 'min:0'],
        ]);
    
        if ($validator->fails()) {
            $this->throwValidationError($validator);
        }
    }
}
