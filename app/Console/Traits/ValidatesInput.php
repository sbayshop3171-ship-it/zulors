<?php

namespace App\Console\Traits;

use Illuminate\Support\Facades\Validator;

trait ValidatesInput
{
	protected function askValid($question, $rules)
	{
		$value = $this->ask($question);

		if($message = $this->validateInput($rules, $value)) {
			$this->error($message);

			return $this->askValid($question, $rules);
		}

		return $value;
	}


	protected function validateInput($rules, $value)
	{
		$validator = Validator::make([
			'cliInput' => $value
		], [
			'cliInput' => $rules
		], attributes: [
			'cliInput' => 'input value'
		]);

		return $validator->fails() ? $validator->errors()->first('cliInput') : null;
	}
}
