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

namespace App\Http\Controllers\Api\Admin;

use Throwable;
use App\Models\User;
use App\Enums\BlacklistType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Actions\User\DeleteUserAction;
use App\Services\Blacklist\BlacklistService;
use App\Traits\Http\Api\SupportsApiResponses;

class AdminController extends Controller
{
    use SupportsApiResponses;

    private BlacklistService $blacklistService;

    public function __construct(BlacklistService $blacklistService) {
        $this->blacklistService = $blacklistService;
    }

    public function deleteProfile(Request $request)
    {
        // TODO
        // Move admin role check to middleware later.

        $userId = $request->integer('user_id');

        if(me()->isRoot()) {

            $deletedUser = User::find($userId);

            try {
                $this->blacklistService->setType(BlacklistType::IP)->add($deletedUser->ip_address);
                $this->blacklistService->setType(BlacklistType::EMAIL)->add($deletedUser->email);
            } catch (Throwable $th) {
                Log::error($th->getMessage());
            }

            (new DeleteUserAction($deletedUser))->execute();

            return $this->responseSuccess([
                'data' => null
            ]);
        }

        else {
            return $this->responseResourceNotFoundError('User', $userId);
        }
    }
}
