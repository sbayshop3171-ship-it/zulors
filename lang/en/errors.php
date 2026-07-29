<?php

return [
    'unauthorized' => 'You are not authorized to perform this action.',
    'confirmation_code_incorrect' => 'The confirmation code is incorrect.',
    'http' => [
        '401' => [
            'title' => 'Unauthorized!',
            'message' => 'Sorry, but you are not authorized to perform this action.'
        ],
        '402' => [
            'title' => 'Payment required!',
            'message' => 'Sorry, but you need to pay to perform this action.'
        ],
        '403' => [
            'title' => 'Not authorized!',
            'message' => 'Sorry, but you are not authorized to perform this action.'
        ],
        '404' => [
            'title' => 'Sorry, this page is not available.',
            'message' => 'You may have used an invalid link or the page has been removed.'
        ],
        '419' => [
            'title' => 'Page expired!',
            'message' => 'Sorry, but the page you are looking for has expired.'
        ],
        '429' => [
            'title' => 'Too many requests!',
            'message' => 'Sorry, but you have made too many requests. Please try again later.'
        ],
        '500' => [
            'title' => 'Internal server error!',
            'message' => 'Sorry, but the server is not responding. Please try again later.'
        ],
        '503' => [
            'title' => 'Service unavailable!',
            'message' => 'Sorry, but the service is not available. Please try again later.'
        ]
    ]
];

