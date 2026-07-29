<?php

return [
    'posting' => [
        'burst_window_seconds' => env('SAFETY_POST_BURST_WINDOW_SECONDS', 60),
        'burst_threshold' => env('SAFETY_POST_BURST_THRESHOLD', 10),
        'freeze_minutes' => env('SAFETY_POST_FREEZE_MINUTES', 15),
    ],
    'content' => [
        'report_spam_score' => env('SAFETY_REPORT_SPAM_SCORE', 8),
        'report_trust_penalty' => env('SAFETY_REPORT_TRUST_PENALTY', 6),
    ],
];
