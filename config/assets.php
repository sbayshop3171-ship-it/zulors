<?php

$fonts = require(var_path('config/assets/fonts.php'));

return [
    'watermark' => [
        // Do not change this path. It is used for the default watermark image.
        // If you want to change the default watermark image, you can change it in the public/assets/watermarks/default.png file.

        'local_path' => 'assets/watermarks/default.png',
        'video' => [
            'position' => 'absolute',
            'y' => 30,
            'x' => 30
        ],
        'image' => [
            'position' => 'bottom-right',
            'padding' => 30
        ]
    ],
    'fonts' => $fonts,
];
