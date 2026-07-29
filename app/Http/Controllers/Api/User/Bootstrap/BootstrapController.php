<?php
/*
|--------------------------------------------------------------------------
| Zulors - The Zulors Web Application.
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

namespace App\Http\Controllers\Api\User\Bootstrap;

use App\Info\Zulors;
use App\Http\Controllers\Controller;
use App\Traits\Http\Api\SupportsApiResponses;

class BootstrapController extends Controller
{
    use SupportsApiResponses;

    public function bootstrap()
    {
        return $this->responseSuccess([
            'data' => [
                'version' => Zulors::VERSION,
                'name' => config('app.name'),
                'author' => [
                    'name' => 'Mansur Terla. Full-Stack Web Developer.',
                    'email' => 'mansurtl.contact@gmail.com'
                ],
                'auth' => [
                    'status' => auth_check(),
                    'user' => $this->getUserData()
                ]
            ]
        ]);
    }

    private function getUserData()
    {
        if(auth_check()) {
            $me = me();

            $userData = [
                'id' => $me->id,
                'name' => $me->name,
                'avatar_url' => $me->avatar_url,
                'cover_url' => $me->cover_url,
                'first_name' => $me->first_name,
                'last_name' => $me->last_name,
                'caption' => $me->getCaption(),
                'username' => $me->username,
                'has_tips' => $me->has_tips,
                'tips' => $me->tips,
                'is_master_account' => $me->isMasterAccount(),
                'is_author' => $me->isAuthor(),
                'verification' => [
                    'status' => $me->verified,
                    'date' => $me->verified_at ? $me->verified_at->getIso() : null
                ],
                'meta' => [
                    'is_admin' => $me->isAdmin(),
                    'is_root' => $me->isRoot()
                ]
            ];

            if($me->isAdmin() || $me->isRoot()) {
                $userData['meta']['admin'] = [
                    'url' => route('admin.dash.index'),
                ];
            }
            
            return $userData;   
        }
        
        return null;
    }
}
