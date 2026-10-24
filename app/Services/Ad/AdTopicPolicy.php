<?php

namespace App\Services\Ad;

use App\Services\Timeline\TopicExtractionService;

class AdTopicPolicy
{
    private const BLOCKED_TOPICS = [
        'health', 'medical', 'medicine', 'mental health', 'depression', 'diabetes', 'cancer',
        'religion', 'islam', 'muslim', 'christianity', 'hinduism', 'buddhism',
        'politics', 'political', 'election', 'party', 'ethnicity', 'race', 'tribe',
        'sexuality', 'sexual orientation', 'lgbt', 'financial hardship', 'debt', 'loan', 'bankruptcy',
    ];

    public function __construct(private TopicExtractionService $topics)
    {
    }

    public function normalizeAllowed(?string $topic): ?string
    {
        if(preg_match('/^(author|language|media_type|duration_bucket|sound_signature):/i', trim((string) $topic))) {
            return null;
        }
        $normalized = $this->topics->normalizeTopic($topic);

        if(blank($normalized) || mb_strlen($normalized) > 80) {
            return null;
        }

        foreach(self::BLOCKED_TOPICS as $blocked) {
            if($normalized === $blocked || str_starts_with($normalized, $blocked . ' ')) {
                return null;
            }
        }

        return $normalized;
    }
}
