<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Traits\Http\Api\SupportsApiResponses;
use App\Validation\User\Account\UserdataRules;

class VerificationController extends Controller
{
    use SupportsApiResponses;
    
    public function verifyUser(Request $request)
    {
        $validator = Validator::make([
            'username' => $request->get('username', null)
        ], [
            'username' => UserdataRules::username()
        ]);

        if($validator->fails()) {
            return $this->responseValidationError([
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ]);
        }

        $username = $request->get('username');
        $userData = User::where('username', $username)->first();

        if(! $userData) {
            return $this->responseResourceNotFoundError('User', $username);
        }

        if($userData->verified) {
            return $this->responseValidationError([
                'message' => 'User is already verified.'
            ]);
        }

        else {
            $userData->update([
                'verified_at' => now(),
                'verified' => true
            ]);
    
            // TODO: Send verification email to user.
            // Send notification to user.
    
            return $this->responseSuccess([
                'message' => 'User verified successfully.'
            ]);
        }

    }
}
