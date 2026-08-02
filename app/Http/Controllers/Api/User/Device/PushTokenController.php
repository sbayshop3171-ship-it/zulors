<?php

namespace App\Http\Controllers\Api\User\Device;

use App\Http\Controllers\Controller;
use App\Models\UserPushToken;
use App\Traits\Http\Api\SupportsApiResponses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PushTokenController extends Controller
{
    use SupportsApiResponses;

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => ['required', 'string', 'max:4096'],
            'provider' => ['nullable', 'string', 'in:fcm'],
            'platform' => ['nullable', 'string', 'in:android,web,ios'],
            'device_id' => ['nullable', 'string', 'max:128'],
            'device_name' => ['nullable', 'string', 'max:120'],
            'app_version' => ['nullable', 'string', 'max:60'],
        ]);

        if($validator->fails()) {
            $this->throwValidationError($validator);
        }

        $token = trim($request->string('token')->toString());
        $tokenHash = hash('sha256', $token);

        $pushToken = UserPushToken::query()->updateOrCreate([
            'token_hash' => $tokenHash,
        ], [
            'user_id' => me()->id,
            'provider' => $request->string('provider', 'fcm')->toString(),
            'platform' => $request->string('platform', 'android')->toString(),
            'token' => $token,
            'device_id' => $request->input('device_id'),
            'device_name' => $request->input('device_name'),
            'app_version' => $request->input('app_version'),
            'last_used_at' => now(),
            'revoked_at' => null,
        ]);

        return $this->responseSuccess([
            'data' => [
                'id' => $pushToken->id,
                'platform' => $pushToken->platform,
                'provider' => $pushToken->provider,
            ],
        ]);
    }

    public function destroy(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => ['required', 'string', 'max:4096'],
        ]);

        if($validator->fails()) {
            $this->throwValidationError($validator);
        }

        me()->pushTokens()
            ->where('token_hash', hash('sha256', trim($request->string('token')->toString())))
            ->delete();

        return $this->responseSuccess([
            'data' => null,
        ]);
    }
}
