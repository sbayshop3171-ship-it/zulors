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

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Traits\Http\Api\SupportsApiResponses;
use App\Validation\User\Account\UserdataRules;
use App\Actions\User\TerminateUserSessionsAction;
use App\Validation\User\Account\UserdataMessages;

class PasswordSettingsController extends Controller
{
    use SupportsApiResponses;
    private $me;

    public function __construct() {
        $this->me = me();
    }

    public function getPasswordSettings() {
        return $this->responseSuccess([
            'data' => [
                'validation_rules' => [
                    'password' => config('user.validation.password')
                ]
            ] 
        ]);
    }

    public function generatePassword() {
        return $this->responseSuccess([
            'data' => [
                'password' => Str::password(20)
            ] 
        ]);
    }

    public function updatePassword(Request $request) {
        $currentPassword = $request->get('password', '');
        $newPassword = $request->get('new_password', '');
        $logoutOtherDevices = $request->boolean('logout_other_devices', false);
        $passwordStrengthControl = config('user.password_strength_control');

        $validator = Validator::make(data: [
            'password' => $currentPassword,
            'new_password' => $newPassword,
        ], rules: [
            'password' => ['required'],
            'new_password' => ($passwordStrengthControl) ? UserdataRules::passwordComplex() : UserdataRules::password()
        ], messages: [
            'password' => UserdataMessages::password(),
            'new_password' => ($passwordStrengthControl) ? UserdataMessages::passwordComplex() : UserdataMessages::password()
        ]);

        if ($validator->fails()) {
            $this->throwValidationError($validator);
        }

        if (Hash::check($currentPassword, $this->me->password)) {

            $this->me->update([
                'password' => bcrypt($newPassword)
            ]);

            if($logoutOtherDevices) {
                $this->logoutOtherDevices();
            }

            return $this->responseSuccess([
                'data' => null
            ]);
        }
        else{
            $errorMessage = __('user.validation.password.incorrect');

            return $this->responseError([
                'message' => $errorMessage,
                'errors' => [
                    'password' => [$errorMessage]
                ]
            ]);
        }
    }

    private function logoutOtherDevices() {
        (new TerminateUserSessionsAction())->execute();
    }
}
