<?php

namespace App\Http\Resources\User\User;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserPreviewResource extends JsonResource
{
    public $with = [];

    public function __construct($resource, $with = [])
    {
        parent::__construct($resource);
        $this->with = $with;
    }

    public function toArray(Request $request): array
    {
        $isMe = (auth_check()) ? $this->id === me()->id : false;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'avatar_url' => $this->avatar_url,
            'is_auth_user' => $isMe,
            'username' => $this->username,
            'caption' => $this->getCaption(),
            'verified' => $this->isVerified(),
            ...$this->with,
        ];
    }
}
