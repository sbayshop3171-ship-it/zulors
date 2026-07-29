<?php
return [
    /*
    |--------------------------------------------------------------------------
    | Default User Signup Settings
    |--------------------------------------------------------------------------
    |
    | In this section, you may define the default settings that will be applied
    | to users during the signup process. These settings include preferences
    | such as whether the user is verified by default, the default language,
    | and default country. These defaults can be adjusted as necessary.
    |
    */

    'online_interval_in_minutes' => 5,
    'gender' => 'male',
    'language' => 'en',
    'avatar' => 'assets/avatars/default-avatar.png',
    'cover' => 'assets/covers/default-cover.png',
    'verified' => false,
    'authorize_users' => false,
    'require' => [
        'last_name' => false,
        'password' => true
    ],
    'theme' => 'dark',
    'country' => 'US',
    'city' => 'New York',
    'category' => null,
    'username_prefix' => 'user',
    'password_strength_control' => true,
    'tips' => [
        'onboarding_bio' => [
            'skippable' => true
        ],
        'onboarding_avatar' => [
            'skippable' => true
        ],
        'onboarding_follow' => [
            'skippable' => true
        ]
    ],
    'otp_validation' => [
        'email' => [
            'enabled' => true,
            'expires_in_minutes' => 30
        ],
        'phone' => [
            'enabled' => true,
            'expires_in_minutes' => 2
        ]
    ],
    'validation' => [
        'password' => [
            'min' => 8,
            'max' => 62
        ],
        'username' => [
            'min' => 2,
            'max' => 32
        ],
        'first_name' => [
            'min' => 2,
            'max' => 32
        ],
        'last_name' => [
            'min' => 2,
            'max' => 32
        ],
        'bio' => [
            'min' => 90,
            'max' => 240
        ],
        'caption' => [
            'min' => 1,
            'max' => 32
        ],
        'avatar' => [
            'mimes' => 'mimes:jpeg,png,jpg,gif,webp',
            'max' => 'max:12288'
        ],
        'cover' => [
            'mimes' => 'mimes:jpeg,png,jpg,gif,webp',
            'max' => 'max:12288'
        ],
        'website' => [
            'max' => 120
        ],
        'email' => [
            'max' => 120
        ],
        'phone' => [
            'required' => false,
            'max' => 15,
            'regex' => "/^\+?([0-9]{1,4})\D?([0-9]{1,3})\D?([0-9]{1,4})\D?([0-9]{1,4})\D?([0-9]{1,4})\D?([0-9]{1,9})?$/"
        ]
    ],
    'avatar_config' => [
        'crop_size' => 256,
        'compress_rate' => 92
    ],
    'cover_config' => [
        'width' => 1500,
        'height' => 500,
        'compress_rate' => 92
    ],
    'auto_follow_list' => env('AUTO_FOLLOW_LIST', ''),
    'onboarding_steps' => [
        'profile' => [
            'component' => 'user.onboarding.profile',
            'is_skippable' => false, // Never change this for this step.
        ],
        'country' => [
            'component' => 'user.onboarding.country',
            'is_skippable' => true,
        ],
        'city' => [
            'component' => 'user.onboarding.city',
            'is_skippable' => true,
        ],
        'username' => [
            'component' => 'user.onboarding.username',
            'is_skippable' => false, // Never change this for this step.
        ],
    ]
];
