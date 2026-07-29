<?php

namespace App\Models\Traits\Base;

trait BaseModel
{
    public function getIsOwnerAttribute()
    {
        return auth_check() ? $this->user_id === me()->id : false;
    }
}
