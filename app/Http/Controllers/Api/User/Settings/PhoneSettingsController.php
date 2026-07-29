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
use App\Services\Sms\SmsSenderService;
use Illuminate\Support\Facades\Validator;
use App\Traits\Http\Api\SupportsApiResponses;
use App\Validation\User\Account\UserdataRules;
use App\Validation\User\Account\UserdataMessages;

class PhoneSettingsController extends Controller
{
    use SupportsApiResponses;

    private $me;
    private $smsSenderService;

    public function __construct(SmsSenderService $smsSenderService) {
        $this->smsSenderService = $smsSenderService;
        $this->me = me();
    }

    public function getPhoneSettings() {
        return $this->responseSuccess([
            'data' => [
                'validation_rules' => [
                    'phone' => config('user.validation.phone')
                ],
                'phone' => $this->me->phone,
                'privacy_settings' => [
                    'phone_privacy' => $this->me->privacySettings->phone_privacy
                ]
            ] 
        ]);
    }

    public function updatePhoneNumber(Request $request)
    {
        $phoneNumber = $request->get('phone', '');
        $phonePrivacy = $request->boolean('phone_privacy', false);

        $validator = Validator::make(data: [
            'phone' => $phoneNumber
        ], rules: [
            'phone' => UserdataRules::phone([
                (config('user.validation.phone.required')) ? 'required' : 'nullable'
            ]),
        ], messages: [
            'phone' => UserdataMessages::phone(),
        ]);

        if ($validator->fails()) {
            $this->throwValidationError($validator);
        }

        $validatedData = $validator->validated();

        $confirmationRequired = false;

        if(empty($validatedData['phone']) || empty(config('user.otp_validation.phone.enabled'))) {
            $this->me->update([
                'phone' => (empty($validatedData['phone'])) ? '' : $validatedData['phone']
            ]);
        }
        else{
            if ($validatedData['phone'] !== $this->me->phone) {
                $confirmationRequired = true;
                $hasRequest = $this->getLastPhoneChangeRequest();

                if(empty($hasRequest)) {
                    $this->purgeExpiredPhoneRequests();
                
                    $this->sendConfirmationCodeSMS($validatedData['phone']);
                }
            }
        }

        $this->updatePhonePrivacy($phonePrivacy);

        return $this->responseSuccess([
            'data' => [
                'phone' => $validatedData['phone'],
                'confirmation_required' => $confirmationRequired
            ]
        ]);
    }

    public function checkPhoneRequest() {
        $lastRequest = $this->getLastPhoneChangeRequest();

        if($lastRequest) {
            return $this->responseSuccess([
                'data' => [
                    'phone' => $lastRequest->identifier,
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

    public function confirmPhoneUpdate(Request $request)
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

        $confirmationData = $this->getLastPhoneChangeRequest();

        if($confirmationData && $confirmationData->code === $validatedData['code']) {
            $this->me->update([
                'phone' => $confirmationData->identifier
            ]);

            $confirmationData->delete();

            return $this->responseSuccess([
                'data' => [
                    'phone' => $confirmationData->identifier
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

    public function resendPhoneConfirmCode()
    {
        $lastRequest = $this->getLastPhoneChangeRequest(false);

        if($lastRequest) {

            $this->purgeExpiredPhoneRequests();

            $this->sendConfirmationCodeSMS($lastRequest->identifier);

            return $this->responseSuccess([
                'data' => [
                    'phone' => $lastRequest->identifier,
                    'time_left' => intval(now()->diffInSeconds(now()->addMinutes(config('user.otp_validation.phone.expires_in_minutes')), false))
                ]
            ]);
        }

        else {
            return $this->responseError([
                'data' => null
            ], Response::HTTP_NOT_FOUND);
        }
    }

    private function sendConfirmationCodeSMS(string $phoneNumber) {
        $confirmationCode = confirmation_unique_code();

        Confirmation::create([
            'user_id' => $this->me->id,
            'identifier' => $phoneNumber,
            'type' => ConfirmationType::PHONE,
            'code' => $confirmationCode,
            'expires_at' => now()->addMinutes(config('user.otp_validation.phone.expires_in_minutes'))
        ]);
        
        // TODO: send SMS
        defer(function () use ($phoneNumber, $confirmationCode) {

            $this->smsSenderService->setNumber($phoneNumber);
            $this->smsSenderService->setMessage(view('sms.user.settings.confirmation-code', [
                'code' => $confirmationCode
            ])->render());
            
            // TODO: send SMS
            // Log SMS send result later after MVP release
            $sendResult = $this->smsSenderService->send();
        });
    }

    private function purgeExpiredPhoneRequests()
    {
        Confirmation::where([
            'user_id' => $this->me->id,
            'type' => ConfirmationType::PHONE,
        ])->where('expires_at', '<=', now())->delete();
    }

    private function getLastPhoneChangeRequest($expired = true) {
        return Confirmation::where([
            'user_id' => $this->me->id,
            'type' => ConfirmationType::PHONE,
        ])->when($expired, function ($query) {
            $query->where('expires_at', '>=', now());
        })->first();
    }

    private function updatePhonePrivacy($value = false) {
        $this->me->privacySettings()->update([
            'phone_privacy' => $value
        ]);
    }
}
