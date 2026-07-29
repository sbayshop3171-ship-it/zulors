<?php

return [
    'index_title' => 'Mobile App Settings',
    'form' => [
        'service_worker_content' => 'Service Worker Code',
        'service_worker_content_helper' => 'Do not edit this code if you are not sure what you are doing.',
        'pwa_icons' => 'PWA App Icons',
        'android_link' => 'Android Link',
        'manifest_content' => 'Manifest JSON',
        'manifest_content_helper' => 'If you have enabled PWA, you can add the manifest content here. Do not edit icons section, it is automatically generated based on the uploaded icons.',
        'android_link_helper' => 'If you have a Android app, you can add the link here. Keep empty to hide the link.',
        'ios_link' => 'iOS Link',
        'ios_link_helper' => 'If you have a iOS app, you can add the link here. Keep empty to hide the link.',
        'pwa_enabled' => 'PWA Enabled',
        'pwa_enabled_helper' => 'PWS is already included in the platform. You can enable it to allow users to install the app on their devices.',
    ],
    'validation' => [
        'pwa_icon_not_found' => 'PWA icon not found.',
    ],
    'callouts' => [
        'pwa_icons' => [
            'title' => 'Upload PWA App Icon',
            'caption' => 'Upload your app icons one at a time. Each file must be a PNG image named exactly by its dimensions. E.g. 192x192.png, 512x512.png, etc.',
        ],
    ],
    'prompts' => [
        'delete_pwa_icon' => [
            'content' => 'Are you sure you want to delete this icon?',
        ],
    ],
];
