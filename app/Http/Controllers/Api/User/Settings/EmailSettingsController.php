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

use App\Models\Confirmation;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Enums\ConfirmationType;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use App\Traits\Http\Api\SupportsApiResponses;
use App\Validation\User\Account\UserdataRules;
use App\Mail\User\Settings\ConfirmationCodeMail;
use App\Validation\User\Account\UserdataMessages;

class EmailSettingsController extends Controller
{
    use SupportsApiResponses;
    
    private $me;

    public function __construct() {
        $this->me = me();
    }

    public function getEmailSettings() {

        return $this->responseSuccess([
            'data' => [
                'validation_rules' => [
                    'email' => config('user.validation.email')
                ],
                'email' => $this->me->email,
                'privacy_settings' => [
                    'email_privacy' => $this->me->privacySettings->email_privacy
                ]
            ] 
        ]);
    }

    public function updateEmailAddress(Request $request)
    {
        $emailAddress = $request->get('email', '');
        $emailPrivacy = $request->boolean('email_privacy', false);

        $validator = Validator::make(data: [
            'email' => $emailAddress
        ], rules: [
            'email' => UserdataRules::email(),
        ], messages: [
            'email' => UserdataMessages::email(),
        ]);

        if ($validator->fails()) {
            $this->throwValidationError($validator);
        }

        $validatedData = $validator->validated();
        $confirmationRequired = false;

        if(empty(config('user.otp_validation.email.enabled'))) {
            $this->me->update([
                'email' => $validatedData['email']
            ]);
        }
        else{
            if ($validatedData['email'] !== $this->me->email) {
                $hasRequest = $this->getLastEmailChangeRequest();
                $confirmationRequired = true;
    
                if(empty($hasRequest)) {
                    $this->purgeExpiredEmailRequests();
        
                    $this->sendConfirmationCodeEmail($validatedData['email']);
                }
            }
        }

        $this->updateEmailPrivacy($emailPrivacy);

        return $this->responseSuccess([
            'data' => [
                'email' => $validatedData['email'],
                'confirmation_required' => $confirmationRequired
            ]
        ]);
    }

    public function resendEmailConfirmCode()
    {
        $lastRequest = $this->getLastEmailChangeRequest(false);

        if($lastRequest) {

            $this->purgeExpiredEmailRequests();

            $this->sendConfirmationCodeEmail($lastRequest->identifier);

            return $this->responseSuccess([
                'data' => [
                    'email' => $lastRequest->identifier,
                    'time_left' => intval(now()->diffInSeconds(now()->addMinutes(config('user.otp_validation.email.expires_in_minutes')), false))
                ]
            ]);
        }

        else {
            return $this->responseError([
                'data' => null
            ], Response::HTTP_NOT_FOUND);
        }
    }

    public function checkEmailRequest()
    {
        $lastRequest = $this->getLastEmailChangeRequest();

        if($lastRequest) {
            return $this->responseSuccess([
                'data' => [
                    'email' => $lastRequest->identifier,
                    'time_left' => intval(now()->diffInSeconds($lastRequest->expires_at, false))
                ]
            ]);
        }

        else {
            return $this->responseError([
                'data' => null
            ], Response::HTTP_NOT_FOUND);
        }
    }

    public function confirmEmailUpdate(Request $request)
    {
        $validator = Validator::make(data: [
            'code' => $request->get('code', ''),
        ], rules: [
            'code' => ['required', 'string', 'size:6'],
        ]);

        if ($validator->fails()) {
            $this->throwValidationError($validator);
        }

        $validatedData = $validator->validated();

        $confirmationData = $this->getLastEmailChangeRequest();

        if($confirmationData && $confirmationData->code === $validatedData['code']) {
            $this->me->update([
                'email' => $confirmationData->identifier
            ]);

            $confirmationData->delete();

            return $this->responseSuccess([
                'data' => [
                    'email' => $confirmationData->identifier
                ]
            ]);
        }

        else {
            return $this->responseError([
                'data' => null,
                'message' => __('errors.confirmation_code_incorrect')
            ], Response::HTTP_NOT_FOUND);
        }
    }


    private function sendConfirmationCodeEmail(string $emailAddress) {
        $confirmationCode = confirmation_unique_code();

        Confirmation::create([
            'user_id' => $this->me->id,
            'identifier' => $emailAddress,
            'type' => ConfirmationType::EMAIL,
            'code' => $confirmationCode,
            'expires_at' => now()->addMinutes(config('user.otp_validation.email.expires_in_minutes'))
        ]);
        
        // TODO: send email
        defer(function () use ($emailAddress, $confirmationCode) {
            Mail::to($emailAddress)->send(new ConfirmationCodeMail([
                'title' => __('auth.hi_there'),
                'code' => $confirmationCode,
                'subTitle' => __('email.code.email.sub_title'),
                'description' => __('email.code.email.description'),
                'ignoreEmail' => __('email.code.email.ignore_email')
            ]));
        });
    }

    private function purgeExpiredEmailRequests()
    {
        Confirmation::where([
            'user_id' => $this->me->id,
            'type' => ConfirmationType::EMAIL,
        ])->where('expires_at', '<=', now())->delete();
    }

    private function getLastEmailChangeRequest($expired = true) {
        return Confirmation::where([
            'user_id' => $this->me->id,
            'type' => ConfirmationType::EMAIL,
        ])->when($expired, function ($query) {
            $query->where('expires_at', '>=', now());
        })->first();
    }

    private function updateEmailPrivacy($value = false) {
        $this->me->privacySettings()->update([
            'email_privacy' => $value
        ]);
    }
}
