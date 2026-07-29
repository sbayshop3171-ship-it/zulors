<?php

namespace App\Support;

class Json
{
    public static function encode(object|array $serializable):string
    {
        return json_encode($serializable);
    }
}
