<?php

namespace App\Http\Controllers\Api\User\Relations;

use App\Constants\Relationship;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Relations\MuteService;
use App\Traits\Http\Api\SupportsApiResponses;
use Exception;
use Illuminate\Http\Request;

class MuteController extends Controller
{
    use SupportsApiResponses;

    public function muteUser(Request $request)
    {
        $request->validate([
            'muted_id' => ['required', 'integer'],
        ]);

        $mutedId = $request->integer('muted_id', 0);
        $mutedData = User::activeById($mutedId)->first();

        if(! $mutedData) {
            return $this->responseResourceNotFoundError('User', $mutedId);
        }

        try {
            $muteService = new MuteService(me(), $mutedData);
            $isMuted = $muteService->isMuted();

            if($isMuted) {
                $muteService->unmute();
            }
            else {
                $muteService->mute();
            }

            return $this->responseSuccess([
                'data' => [
                    'relationship' => [
                        Relationship::MUTING_GROUP => [
                            Relationship::MUTING => ! $isMuted,
                        ]
                    ]
                ]
            ]);
        } catch (Exception $e) {
            return $this->responseValidationError([
                'message' => $e->getMessage(),
                'errors' => [
                    'muted_id' => [$e->getMessage()]
                ]
            ]);
        }
    }
}
