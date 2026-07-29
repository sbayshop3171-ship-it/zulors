<?php

namespace App\Traits\Http\Controllers\Api\User\Chat;

use App\Models\Chat;
use App\Models\Group;
use Illuminate\Support\Str;
use App\Enums\Chat\ChatType;
use App\Enums\Chat\GroupStatus;

trait InteractsWithDraftGroup
{
	private $draftGroup;

    private function fetchOrInitializeDraftGroup()
    {
        // Find if there is a draft group.
        // If there is no draft group, create one.
        
        $this->draftGroup = $this->fetchDraftGroup();
    
        if(empty($this->draftGroup) || empty($this->draftGroup->chat)) {
            // Create a new chat and link it to the draft group.
            $chatData = Chat::create([
                'chat_id' => Str::uuid(),
                'type' => ChatType::GROUP,
                'created_at' => now(),
                'last_activity' => null
            ]);
    
            $chatData->group()->create([
                'user_id' => me()->id,
                'status' => GroupStatus::DRAFT
            ]);
    
            $chatData->addParticipant(me()->id);
    
            $this->draftGroup = $this->fetchDraftGroup();
        }
    }

    private function fetchDraftGroup()
    {
        return Group::where('status', GroupStatus::DRAFT)
            ->where('user_id', me()->id)
            ->first();
    }
}
