<?php

return [
    'validation' => [
        'username' => [
            'required' => 'The username can not be empty.',
            'unique' => 'This username is already taken.',
            'regex' => 'The username can only contain letters, numbers, underscores, and dots.',
            'min' => 'The username is too short. It must be at least :min characters.',
            'max' => 'The username is too long. It must not exceed :max characters.'
        ],
        'first_name' => [
            'required' => 'The name field is required.',
            'min' => 'The name must be at least :min characters.',
            'max' => 'The name must not exceed :max characters.'
        ],
        'last_name' => [
            'min' => 'The surname must be at least :min characters.',
            'max' => 'The surname must not exceed :max characters.'
        ],
        'bio' => [
            'min' => 'The bio must be at least :min characters.',
            'max' => 'The bio must not exceed :max characters.'
        ],
        'gender' => [
            'in' => 'The gender must be either "Male" or "Female".'
        ],
        'website' => [
            'url' => 'The website must be a valid URL.',
            'max' => 'The website must not exceed :max characters.'
        ],
        'email' => [
            'required' => 'The email address filed can not be empty.',
            'email' => 'The email address is not valid.',
            'max' => 'The email address must not exceed :max characters.',
            'unique' => 'This email address is already taken.'
        ],
        'phone' => [
            'required' => 'The phone number field can not be empty.',
            'regex' => 'The phone number is not valid.',
            'max' => 'The phone number must not exceed :max characters.',
            'string' => 'The phone number is not valid.'
        ],
        'password' => [
            'required' => 'The password field can not be empty.',
            'string' => 'The password is not valid format.',
            'min' => 'The password must be at least :min characters.',
            'max' => 'The password must not exceed :max characters.',
            'incorrect' => 'The password is incorrect.',
            'mixed_case' => 'The password must include both uppercase and lowercase letters.',
            'letters' => 'The password must include at least one letter.',
            'numbers' => 'The password must include at least one number.',
            'symbols' => 'The password must include at least one special character.'
        ],
        'caption' => [
            'min' => 'The caption must be at least :min characters.',
            'max' => 'The caption must not exceed :max characters.'
        ]
    ],
    'account_deleted' => 'Your account has been deleted successfully. We hope to see you again soon.',
];