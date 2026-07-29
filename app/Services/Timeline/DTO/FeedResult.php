<?php

namespace App\Services\Timeline\DTO;

use Illuminate\Support\Collection;

class FeedResult
{
    public function __construct(
        public Collection $posts,
        public array $meta = []
    ) {
    }
}
