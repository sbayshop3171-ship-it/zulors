<?php

return [
	'group' => [
		'avatar' => 'assets/avatars/default-avatar.png',
		'invite_expire_days' => 7
	],
    'validation' => [
        'message' => [
            'media_type' => [
                'types' => ['image', 'video', 'audio', 'document'],
            ],
            'message_type' => [
                'types' => ['location'],
            ],
            'media' => [
                'mimes' => join(',', [
                    'mp4',
                    'm4a',
                    'mp3',
                    'wav',
                    'aac',
                    'ogg',
                    'avi',
                    'mpeg',
                    'mov',
                    'webm',
                    'gif',
                    'jpeg',
                    'png',
                    'jpg',
                    'webp',
                    'heic',
                    'heif',
                    'heif-sequence',
                    'heic-sequence',
                    'pdf',
                    'doc',
                    'docx',
                    'ppt',
                    'pptx',
                    'xls',
                    'xlsx',
                    'txt',
                    'zip',
                    'rar',
                ]),
                'mimetypes' => join(',', [
                    'audio/aac',
                    'audio/mp4',
                    'audio/mpeg',
                    'audio/mp3',
                    'audio/ogg',
                    'audio/webm',
                    'audio/wav',
                    'audio/x-m4a',
                    'audio/x-wav',
                    'video/mp4',
                    'video/avi',
                    'video/mpeg',
                    'video/quicktime',
                    'video/webm',
                    'image/gif',
                    'image/jpeg',
                    'image/png',
                    'image/webp',
                    'image/heic',
                    'image/heif',
                    'image/heif-sequence',
                    'image/heic-sequence',
                    'application/pdf',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/vnd.ms-powerpoint',
                    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'text/plain',
                    'application/zip',
                    'application/x-zip-compressed',
                    'application/x-rar-compressed',
                    'application/vnd.rar',
                ]),
                'max' => '512000' // 512MB
            ],
        ]
    ],
	'message' => [
		'validation' => [
			'content' => [
				'min' => 1,
				'max' => 2200
			],
		]
	],
	'colors' => [
		'#C7508B',
		'#D67722',
		'#CC5049',
		'#309eba',
		'#40a920',
		'#955cdb'
	],
	'sounds' => [
		'active_chat_message_received' => 'assets/sounds/chats/active-chat-message-received.mp3',
		'background_chat_message_received' => 'assets/sounds/chats/background-chat-message-received.mp3',
		'chat_message_sent' => 'assets/sounds/chats/chat-message-sent.mp3',
    ],
    'processing' => [
        'image' => [
            'compress_rate' => (int) env('CHAT_IMAGE_QUALITY', 92),
        ],
        'video_thumbnail' => [
            'compress_rate' => (int) env('CHAT_VIDEO_THUMBNAIL_QUALITY', 90),
        ],
        'video' => [
            'crf' => (int) env('CHAT_VIDEO_CRF', 20),
            'preset' => env('CHAT_VIDEO_PRESET', 'medium'),
            'audio_bitrate' => (int) env('CHAT_VIDEO_AUDIO_BITRATE', 128),
            'square_size' => (int) env('CHAT_VIDEO_SQUARE_SIZE', 720),
        ],
        'audio' => [
            'preferred_extension' => env('CHAT_AUDIO_PREFERRED_EXTENSION', 'mp3'),
            'bitrate' => (int) env('CHAT_AUDIO_BITRATE', 96),
        ],
    ],
    'enable_video_compression' => env('CHAT_ENABLE_VIDEO_COMPRESSION', true),
];
