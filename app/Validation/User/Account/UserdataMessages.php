<?php

namespace App\Validation\User\Account;

class UserdataMessages
{
    public static function username(): array
    {
        return [
            'required' => __('user.validation.username.required'),
            'unique' => __('user.validation.username.unique'),
            'regex' => __('user.validation.username.regex'),
            'min' => __('user.validation.username.min', ['min' => config('user.validation.username.min')]),
            'max' => __('user.validation.username.max', ['min' => config('user.validation.username.max')]),
        ];
    }

    public static function caption(): array
    {
        return [
            'min' => __('user.validation.caption.min', ['min' => config('user.validation.caption.min')]),
            'max' => __('user.validation.caption.max', ['min' => config('user.validation.caption.max')]),
        ];
    }

    public static function password(): array
    {
        return [
            'required' => __('user.validation.password.required'),
            'string' => __('user.validation.password.string'),
            'min' => __('user.validation.password.min', ['min' => config('user.validation.password.min')]),
            'max' => __('user.validation.password.max', ['min' => config('user.validation.password.max')])
        ];
    }

    public static function passwordComplex(): array
    {
        return [
            'required' => __('user.validation.password.required'),
            'string' => __('user.validation.password.string'),
            'min' => __('user.validation.password.min', ['min' => config('user.validation.password.min')]),
            'max' => __('user.validation.password.max', ['min' => config('user.validation.password.max')]),
            'mixedCase' => __('user.validation.password.mixed_case'),
            'letters' => __('user.validation.password.letters'),
            'numbers' => __('user.validation.password.numbers'),
            'symbols' => __('user.validation.password.symbols')
        ];
    }

    public static function firstName(): array
    {
        return [
            'required' => __('user.validation.first_name.required'),
            'min' => __('user.validation.first_name.min', ['min' => config('user.validation.first_name.min')]),
            'max' => __('user.validation.first_name.max', ['min' => config('user.validation.first_name.max')]),
        ];
    }

    public static function lastName(): array
    {
        return [
            'min' => __('user.validation.last_name.min', ['min' => config('user.validation.last_name.min')]),
            'max' => __('user.validation.last_name.max', ['min' => config('user.validation.last_name.max')]),
        ];
    }

    public static function bio(): array
    {
        return [
            'min' => __('user.validation.bio.min', ['min' => config('user.validation.bio.min')]),
            'max' => __('user.validation.bio.max', ['min' => config('user.validation.bio.max')]),
        ];
    }

    public static function gender(): array
    {
        return [
            'in' => __('user.validation.gender.in'),
        ];
    }

    public static function website(): array
    {
        return [
            'url' => __('user.validation.website.url'),
            'max' => __('user.validation.website.max', ['max' => config('user.validation.website.max')]),
        ];
    }

    public static function email(): array
    {
        return [
            'required' => __('user.validation.email.required'),
            'email' => __('user.validation.email.email'),
            'max' => __('user.validation.email.max', ['max' => config('user.validation.email.max')]),
            'unique' => __('user.validation.email.unique')
        ];
    }

    public static function phone(): array
    {
        return [
            'required' => __('user.validation.phone.required'),
            'regex' => __('user.validation.phone.regex'),
            'max' => __('user.validation.phone.max', ['max' => config('user.validation.phone.max')]),
            'string' => __('user.validation.phone.string'),
        ];
    }
}