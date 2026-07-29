<?php
/*
|--------------------------------------------------------------------------
| Zulors - The Ultimate Zulors Web Application.
|--------------------------------------------------------------------------
| Author: Mansur Terla. Full-Stack Web Developer, UI/UX Designer.
| Website: www.terla.me
| E-mail: mansurtl.contact@gmail.com
| Instagram: @mansur_terla
| Telegram: @mansurtl_contact
|--------------------------------------------------------------------------
| Copyright (c)  Zulors. All rights reserved.
|--------------------------------------------------------------------------
*/

use App\Models\Chat;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('App.Models.Chat.{chatId}', function ($user, $chatId) {
    $chatId = (Str::isUuid($chatId) ? $chatId : null);

    $chatData = Chat::where('chat_id', $chatId)->first();
    
    if($chatData) {
        return $chatData->participants()->where('user_id', $user->id)->exists();
    }

    return false;
});
