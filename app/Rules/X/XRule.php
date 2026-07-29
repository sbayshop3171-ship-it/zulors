<?php

namespace App\Rules\X;

use Ramsey\Uuid\Type\Decimal;

class XRule
{
    /**
     * Class XRule
     *
     * This class is a simple text formatter designed to generate and concatenate
     * rule descriptions in a cleaner and more readable way. It helps in building
     * dynamic validation rule descriptions, such as minimum and maximum lengths,
     * and presents them in a user-friendly format. 
     *
     * Note: This class does not perform validation; it is solely intended for 
     * creating descriptive text related to rules.
     * 
     * (C) Mansur Terla
     */

    public static function join(string|int $key, string|int|float|decimal $value): string
    {
        return join(':', [$key, $value]);
    }

    public static function requiredOrNullable(bool $statement): string
    {
        return ($statement == true) ? 'required' : 'nullable';
    }
}
