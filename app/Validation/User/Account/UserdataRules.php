<?php

namespace App\Validation\User\Account;

use App\Rules\X\XRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserdataRules
{
    public static function username(): array
    {
        $rules = [
            'bail',
            'required',
            'string', 
            'regex:/^[a-zA-Z0-9._]+$/',
            XRule::join('max', config('user.validation.username.max')),
            XRule::join('min', config('user.validation.username.min'))
        ];

        if(auth_check()) {
            array_push($rules, Rule::unique('users', 'username')->ignore(me()->username, 'username'));
        }

        return $rules;
    }

    public static function caption(): array
    {
        return [
            'bail',
            'nullable',
            'string', 
            XRule::join('max', config('user.validation.caption.max')),
            XRule::join('min', config('user.validation.caption.min'))
        ];
    }

    public static function password(): array
    {
        return [
            'bail',
            'required',
            'string',
            XRule::join('max', config('user.validation.password.max')),
            XRule::join('min', config('user.validation.password.min'))
        ];
    }

    public static function passwordComplex(): array
    {
        return [
            'bail',
            'required',
            'string',
            XRule::join('max', config('user.validation.password.max')),
            XRule::join('min', config('user.validation.password.min')),
            // TODO : Add password strength control
        ];
    }

    public static function firstName(): array
    {
        return [
            'bail',
            'required', 
            'string', 
            XRule::join('min', config('user.validation.first_name.min')), 
            XRule::join('max', config('user.validation.first_name.max'))
        ];
    }

    public static function lastName(): array
    {
        return [
            'nullable', 
            'string', 
            XRule::join('min', config('user.validation.last_name.min')), 
            XRule::join('max', config('user.validation.last_name.max'))
        ];
    }

    public static function bio(): array
    {
        return [
            'bail',
            'nullable', 
            'string', 
            XRule::join('min', config('user.validation.bio.min')), 
            XRule::join('max', config('user.validation.bio.max'))
        ];
    }

    public static function website(): array
    {
        return [
            'bail',
            'nullable', 
            'url', 
            'string', 
            XRule::join('max', config('user.validation.website.max'))
        ];
    }

    public static function gender(): array
    {
        return [
            'bail',
            'nullable',
            'string', 
            Rule::in(['male', 'female', 'not-specified'])
        ];
    }

    public static function email(): array
    {
        return [
            'bail',
            'required', 
            'string',
            'email', 
            XRule::join('max', config('user.validation.email.max')),
            Rule::unique('users', 'email')->ignore(me()->id)
        ];
    }

    public static function phone(array $additionalRules = []): array
    {
        return array_merge([
            'bail',
            'string',
            XRule::join('regex', config('user.validation.phone.regex')),
            XRule::join('max', config('user.validation.phone.max')),
        ], $additionalRules);
    }
}