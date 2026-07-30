<?php

namespace App\Services\TestContent;

use App\Models\User;

class OriginalTestArticleFactory
{
    private const ARTICLES = [
        [
            'title' => 'A clearer way to plan a busy week',
            'body' => 'A practical plan starts with one visible outcome. Choose the result that matters most, write the next three actions, and leave room for the work that will change once the week begins.',
            'tags' => '#planning #focus #work',
        ],
        [
            'title' => 'Small systems create reliable progress',
            'body' => 'Big goals become manageable when the next action is small enough to finish today. A short review, a clean checklist, and a realistic deadline are often more useful than a perfect plan.',
            'tags' => '#progress #productivity #habits',
        ],
        [
            'title' => 'Good communication removes hidden friction',
            'body' => 'Clear updates help people make decisions without extra meetings. Share the context, name the decision needed, and finish with the next owner so everyone knows what happens next.',
            'tags' => '#communication #teamwork #leadership',
        ],
        [
            'title' => 'Learning becomes useful when it is applied',
            'body' => 'After reading something valuable, turn one idea into a small experiment. A note is helpful, but a real result comes from testing the idea in your work and reviewing what changed.',
            'tags' => '#learning #growth #skills',
        ],
        [
            'title' => 'A calm workspace supports better decisions',
            'body' => 'A simple workspace is not about being minimal for its own sake. It helps important information stand out, reduces repeated searching, and makes it easier to finish one task before starting another.',
            'tags' => '#workflow #clarity #productivity',
        ],
        [
            'title' => 'Consistency is a practical advantage',
            'body' => 'Reliable effort compounds quietly. A useful habit does not need to be dramatic; it only needs to be easy to repeat when the day is busy and motivation is low.',
            'tags' => '#consistency #mindset #growth',
        ],
        [
            'title' => 'Better questions lead to better solutions',
            'body' => 'Before rushing to a solution, define the real problem in one sentence. Asking who is affected, what success looks like, and what constraints matter can prevent weeks of unnecessary work.',
            'tags' => '#problemsolving #strategy #thinking',
        ],
        [
            'title' => 'Protect time for the work that matters',
            'body' => 'Attention is a limited resource. Group small tasks together, silence nonessential interruptions for a short block, and give important work a clear start and finish time.',
            'tags' => '#focus #timemanagement #deepwork',
        ],
        [
            'title' => 'Useful feedback is specific and timely',
            'body' => 'Strong feedback describes an observed action, explains its effect, and offers a next step. This keeps the conversation practical and makes improvement feel possible instead of personal.',
            'tags' => '#feedback #collaboration #growth',
        ],
        [
            'title' => 'Healthy teams make ownership visible',
            'body' => 'Work moves faster when every task has an owner, a simple deadline, and a shared definition of done. Visibility is not micromanagement; it is a way to help people support one another.',
            'tags' => '#teams #ownership #operations',
        ],
        [
            'title' => 'A good digital habit starts with a pause',
            'body' => 'Before reacting to a notification, pause long enough to decide whether it needs attention now. That small choice can protect focus while keeping important conversations responsive.',
            'tags' => '#digitalwellbeing #focus #habits',
        ],
        [
            'title' => 'Good ideas become stronger through revision',
            'body' => 'The first version only gives you something to improve. Review it with fresh eyes, remove what is not serving the message, and make the useful parts easier for another person to understand.',
            'tags' => '#writing #creativity #improvement',
        ],
    ];

    private const CLOSERS = [
        'What is one small change that would make this easier this week?',
        'The useful version is the one you can repeat tomorrow.',
        'Simple systems leave more room for thoughtful work.',
        'Progress is easier to see when the next step is clear.',
        'A small improvement today can remove a larger problem later.',
        'Clarity is often the fastest path to momentum.',
    ];

    public function make(User $user, string $campaignKey): array
    {
        $seed = sprintf('%u', crc32($campaignKey . ':' . $user->id));
        $articleIndex = (int) $seed % count(self::ARTICLES);
        $closerIndex = (int) floor(((int) $seed / count(self::ARTICLES))) % count(self::CLOSERS);
        $article = self::ARTICLES[$articleIndex];

        return [
            'content_key' => 'article-' . ($articleIndex + 1) . '-closer-' . ($closerIndex + 1),
            'title' => $article['title'],
            'content' => implode("\n\n", [
                $article['body'],
                self::CLOSERS[$closerIndex],
                $article['tags'],
            ]),
        ];
    }
}
