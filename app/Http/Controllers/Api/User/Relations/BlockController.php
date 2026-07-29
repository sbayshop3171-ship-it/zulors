<?php

namespace App\Http\Controllers\Api\User\Relations;

use App\Constants\Relationship;
use App\Http\Controllers\Controller;
use App\Http\Resources\User\Relation\BlockResource;
use App\Models\Block;
use App\Models\User;
use App\Services\Relations\BlockService;
use App\Traits\Http\Api\SupportsApiResponses;
use Exception;
use Illuminate\Http\Request;

class BlockController extends Controller
{
    use SupportsApiResponses;

    public function blockUser(Request $request)
    {
        $request->validate([
            'blocked_id' => ['required', 'integer'],
        ]);

        $blockedId = $request->integer('blocked_id', 0);
        $blockedData = User::activeById($blockedId)->first();

        if(! $blockedData) {
            return $this->responseResourceNotFoundError('User', $blockedId);
        }

        try {
            $blockService = new BlockService(me(), $blockedData);
            $isBlocked = $blockService->isBlocked();

            if($isBlocked) {
                $blockService->unblock();
            }
            else {
                $blockService->block();
            }

            return $this->responseSuccess([
                'data' => [
                    'relationship' => [
                        Relationship::BLOCK_GROUP => [
                            Relationship::BLOCKING => ! $isBlocked
                        ]
                    ]
                ]
            ]);
        } catch (Exception $e) {
            return $this->responseValidationError([
                'message' => $e->getMessage(),
                'errors' => [
                    'blocked_id' => [$e->getMessage()]
                ]
            ]);
        }
    }

    public function getBlockedUsers(Request $request)
    {
        $blockedUsers = Block::where('blocker_id', me()->id)->with('blocked')->get();

        return $this->responseSuccess([
            'data' => $blockedUsers->map(function ($block) {
                return BlockResource::make($block);
            })->toArray()
        ]);
    }
}
