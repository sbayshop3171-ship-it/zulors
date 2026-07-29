<?php

return [
    'validation' => [
        'add_post_text' => 'Please add some words to share your thoughts.',
        'add_poll_text' => 'Every poll needs a question. Please add one.',
        'image_limit_reach' => 'You can add only :max_images_count images to this post.',
        'poll' => [
            'options_required' => 'Please provide at least two options for the poll.',
            'invalid_data' => 'The poll data is invalid.',
            'min_options' => 'A poll must have at least :min options.',
            'max_options' => 'A poll cannot have more than :max options.',
            'option_text' => 'Each poll option must have text.',
            'option_text_min' => 'Poll option text must be at least :min character.',
            'option_text_max' => 'Poll option text cannot exceed :max characters.'
        ],
        'wrong_type_attachment' => 'Can not attach :file_type file to this type of post.',
    ]
];