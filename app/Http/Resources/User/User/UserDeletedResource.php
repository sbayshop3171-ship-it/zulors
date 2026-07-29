<?php

namespace App\Http\Resources\User\User;

use Illuminate\Http\Request;

class UserDeletedResource
{
    public static function make(): array
    {
        return [
            'id' => 0,
            'name' => 'Deleted Account',
            'username' => 'deleted',
            'caption' => 'Deleted',
            'verified' => false,
            'avatar_url' => asset(config('user.avatar'))
        ];
    }
}
