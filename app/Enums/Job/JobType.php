<?php

namespace App\Enums\Job;

enum JobType: string
{
    case VACANCY = 'vacancy';
    case PROJECT = 'project';

    public function label(): string
    {
        return match ($this) {
            self::VACANCY => 'Vacancy',
            self::PROJECT => 'Project',
        };
    }

	public function emoji(): string
	{
		return match ($this) {
			self::VACANCY => '💼',
			self::PROJECT => '📑',
		};
	}

    public static function types()
	{
		return [
			[
				'key' => self::VACANCY->value,
				'value' => __('business/labels.type_labels.vacancy')
			],
			[
				'key' => self::PROJECT->value,
				'value' => __('business/labels.type_labels.project')
			]
		];
	}
}
