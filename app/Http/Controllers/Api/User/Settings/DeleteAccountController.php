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

namespace App\Http\Controllers\Api\User\Settings;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Actions\User\DeleteUserAction;
use App\Models\AccountDeletionFeedback;
use Illuminate\Support\Facades\Validator;
use App\Traits\Http\Api\SupportsApiResponses;

class DeleteAccountController extends Controller
{
    use SupportsApiResponses;

    private $me;

    public function __construct()
    {
        $this->me = me();
    }

    public function deleteAccount(Request $request) {

        $validator = Validator::make(data: [
            'password' => $request->get('password', ''),
            'message' => $request->get('message', '')
        ], rules: [
            'password' => ['required', 'bail', 'string', 'max:120'],
            'message' => ['nullable', 'bail', 'string', 'max:1200']
        ], attributes: [
            'password' => __('labels.password'),
            'message' => __('labels.message')
        ]);

        if($validator->fails()) {
            return $this->throwValidationError($validator);
        }
        else{
            if(Hash::check($request->get('password'), $this->me->password)) {
                $deleteReasonMessage = $request->get('message', '');

                if($deleteReasonMessage) {
                    AccountDeletionFeedback::create([
                       'feedback_message' => $deleteReasonMessage,
                       'username' => $this->me->username,
                       'full_name' => $this->me->name,
                       'email' => $this->me->email,
                       'phone' => $this->me->phone,
                       'registered_at' => $this->me->getCreatedAt()->getTimestamp(),
                       'publications_count' => $this->me->publications_count,
                       'followers_count' => $this->me->followers_count,
                       'following_count' => $this->me->following_count,
                       'ip_address' => $this->me->ip_address,
                       'user_agent' => $request->header('User-Agent'),
                       'deleted_at' => now()
                    ]);
                }
    
                (new DeleteUserAction($this->me))->execute();

                Auth::guard('web')->logout();
    
                return $this->responseSuccess([
                    'message' => __('user.account_deleted')
                ]);
            }
            else {
                return $this->responseError([
                    'message' => __('user.validation.password.incorrect'),
                    'errors' => [
                        'password' => [__('user.validation.password.incorrect')]
                    ]
                ]);
            }
        }
    }
}
