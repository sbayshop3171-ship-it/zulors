<?php

namespace App\Http\Controllers\Api\User\Chat;

use Throwable;
use App\Models\Chat;
use App\Models\User;
use App\Models\ChatInvite;
use Illuminate\Support\Str;
use App\Enums\Chat\ChatType;
use Illuminate\Http\Request;
use App\Database\Configs\Table;
use App\Enums\Chat\GroupStatus;
use App\Enums\Chat\GroupVisibility;
use App\Enums\Chat\ChatInviteStatus;
use App\Http\Controllers\Controller;
use App\Actions\Chat\DeleteChatAction;
use App\Actions\Chat\DeleteGroupAction;
use App\Actions\Chat\DeleteMessageAction;
use App\Traits\Http\Api\SupportsApiResponses;
use App\Http\Resources\User\Chat\GroupResource;
use Illuminate\Auth\Access\AuthorizationException;
use App\Http\Resources\User\User\UserPreviewResource;
use App\Services\Filesystem\Delete\FileDeleteService;
use App\Services\Filesystem\Upload\ImageUploadService;
use App\Http\Resources\User\Chat\ParticipantCollection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Http\Resources\User\Chat\Editor\EditGroupResource;
use App\Traits\Http\Controllers\Api\User\Chat\InteractsWithDraftGroup;

class GroupController extends Controller
{
    use SupportsApiResponses,
        AuthorizesRequests,
        InteractsWithDraftGroup;

    public function createDraftGroup()
    {
        $this->fetchOrInitializeDraftGroup();
        
        return $this->responseSuccess([
            'data' => EditGroupResource::make($this->draftGroup)
        ]);
    }

    public function getPendingInvitations(string $chatId)
    {
        if(Str::isUuid($chatId)) {

            $chatData = $this->findGroupWithChatId($chatId);
            
            if($chatData) {
                try {
                    $this->authorize('update', $chatData->group);

                    $pendingInvitations = $chatData->invites()->pending()->with('receiver')->latest()->get();

                    return $this->responseSuccess([
                        'data' => $pendingInvitations->map(function($invite) {
                            return [
                                'id' => $invite->id,
                                'relations' => [
                                    'user' => UserPreviewResource::make($invite->receiver)
                                ],
                                'meta' => [
                                    'time_ago' => $invite->created_at->getTimeAgo()
                                ]
                            ];
                        })
                    ]);
                } catch (AuthorizationException $e) {
                    return $this->responseUnauthorizedError();
                } catch (Throwable $th) {
                    return $this->responseError([
                        'message' => $th->getMessage(),
                    ]);
                }
            }
        }

        return $this->responseResourceNotFoundError('Chat', $chatId);
    }

    public function deleteParticipants(Request $request)
    {
        $request->validate([
            'chat_id' => ['required', 'string', 'uuid'],
            'ids' => ['required', 'array'],
            'ids.*' => ['required', 'integer']
        ]);
        
        $chatId = $request->chat_id;
        $participantIds = $request->ids;
        
        $chatData = $this->findGroupWithChatId($chatId);

        if($chatData) {
            try {
                $this->authorize('update', $chatData->group);
                
                $groupOwnerId = $chatData->group->user_id;

                $participants = $chatData->participants()->whereIn('id', $participantIds)->get();

                // TODO: Improve this code.
                // Do not let owner of the group to delete himself from group.

                // Code could be much better on creative mood. 🤣
                // I have wrote this code on very down mood and very bad internet
                // I have to fix it later.

                $participants->each(function($p) use ($groupOwnerId) {
                    if($p->user->id != $groupOwnerId) {
                        $p->delete();
                    }
                });

                return $this->responseSuccess([
                    'data' => null
                ]);
            } catch (AuthorizationException $e) {
                return $this->responseUnauthorizedError();
            } catch (Throwable $th) {
                return $this->responseError([
                    'message' => $th->getMessage(),
                ]);
            }
        }

        return $this->responseResourceNotFoundError('Chat', $chatId);
    }

    public function deleteGroup(Request $request)
    {
        $request->validate([
            'chat_id' => ['required', 'string', 'uuid']
        ]);
        
        $chatId = $request->chat_id;
        
        $chatData = $this->findGroupWithChatId($chatId);

        if($chatData) {
            try {
                $this->authorize('delete', $chatData->group);

                (new DeleteGroupAction($chatData->group))->execute();

                (new DeleteChatAction($chatData))->execute();

                return $this->responseSuccess([
                    'data' => null
                ]);
            } catch (AuthorizationException $e) {
                return $this->responseUnauthorizedError();
            } catch (Throwable $th) {
                return $this->responseError([
                    'message' => $th->getMessage(),
                ]);
            }
        }

        return $this->responseResourceNotFoundError('Chat', $chatId);
    }

    public function inviteParticipants(Request $request)
    {
        $request->validate([
            'chat_id' => ['required', 'string', 'uuid'],
            'ids' => ['required', 'array'],
            'ids.*' => ['required', 'integer']
        ]);
        
        $chatId = $request->chat_id;
        $participantIds = $request->ids;
        
        $chatData = $this->findGroupWithChatId($chatId);

        if($chatData) {
            try {
                $this->authorize('update', $chatData->group);

                $invitees = User::excludeSelf()->active()->whereIn('id', $participantIds)->whereNotIn('id', function ($query) use ($chatData) {
                    // Do not invite users who have already been invited to the group.
                    $query->select('receiver_id')->from(Table::CHAT_INVITES)->where('chat_id', $chatData->id);
                })->get();

                $invitees->each(function ($invitee) use ($chatData) {
                    ChatInvite::create([
                        'chat_id' => $chatData->id,
                        'receiver_id' => $invitee->id,
                        'sender_id' => me()->id,
                        'status' => ChatInviteStatus::PENDING,
                        'expires_at' => now()->addDays(config('chat.group.invite_expire_days')),
                    ]);
                });

                return $this->responseSuccess([
                    'data' => null
                ]);
            } catch (AuthorizationException $e) {
                return $this->responseUnauthorizedError();
            } catch (Throwable $th) {
                return $this->responseError([
                    'message' => $th->getMessage(),
                ]);
            }
        }

        return $this->responseResourceNotFoundError('Chat', $chatId);
    }

    public function acceptInvite(Request $request)
    {
        $request->validate([
            'chat_id' => ['required', 'string', 'uuid']
        ]);

        $chatData = $this->findGroupWithChatId($request->chat_id);

        if($chatData && $chatData->isInvited(me()->id)) {
            $invitationData = $chatData->invites()->where('receiver_id', me()->id)->first();

            $chatData->addParticipant($invitationData->receiver_id);

            $invitationData->delete();

            return $this->responseSuccess([
                'data' => null
            ]);
        }

        return $this->responseResourceNotFoundError('Chat', $request->chat_id);
    }

    public function declineInvitation(Request $request)
    {
        $request->validate([
            'chat_id' => ['required', 'string', 'uuid']
        ]);

        $chatData = $this->findGroupWithChatId($request->chat_id);

        if($chatData && $chatData->isInvited(me()->id)) {
            $invitationData = $chatData->invites()->where('receiver_id', me()->id)->first();

            $invitationData->update([
                'status' => ChatInviteStatus::DECLINED
            ]);

            return $this->responseSuccess([
                'data' => null
            ]);
        }

        return $this->responseResourceNotFoundError('Chat', $request->chat_id);
    }

    public function leaveGroup(Request $request)
    {
        $request->validate([
            'chat_id' => ['required', 'string', 'uuid']
        ]);

        $chatData = $this->findGroupWithChatId($request->chat_id);

        if($chatData) {            
            try {
                $this->authorize('leave', $chatData->group);

                // Delete all messages of the user from the group.
                $chatData->messages()->where('user_id', me()->id)->each(function($messageData) {
                    (new DeleteMessageAction($messageData))->execute();
                });

                $chatData->removeParticipant(me()->id);

                return $this->responseSuccess([
                    'data' => null
                ]);
            } catch (AuthorizationException $e) {
                return $this->responseUnauthorizedError();
            } catch (Throwable $th) {
                return $this->responseError([
                    'message' => $th->getMessage(),
                ]);
            }
        }

        return $this->responseResourceNotFoundError('Chat', $request->chat_id);
    }

    public function searchInvitees(Request $request)
    {
        $request->validate([
            'chat_id' => ['required', 'string', 'uuid'],
            'query' => ['nullable', 'string', 'min:1', 'max:255'],
        ]);

        $chatData = $this->findGroupWithChatId($request->chat_id);

        $searchKeyword = e($request->get('query'));

        if($chatData) {
            $invitees = User::active()->excludeSelf()->whereNotIn('id', function ($query) use ($chatData) {
                $query->select('user_id')->from(Table::CHAT_PARTICIPANTS)->where('chat_id', $chatData->id);
            })->whereNotIn('id', function ($query) use ($chatData) {

                // Do not show users who have already been invited to the group.
                $query->select('receiver_id')->from(Table::CHAT_INVITES)->where('chat_id', $chatData->id);
            })->when(! empty($searchKeyword), function ($query) use ($searchKeyword) {
                
                $query->where(function($query) use ($searchKeyword) {
                    $query->whereLike('username', "%{$searchKeyword}%")
                        ->orWhereLike('first_name', "%{$searchKeyword}%")
                        ->orWhereLike('caption', "%{$searchKeyword}%")
                        ->orWhereLike('bio', "%{$searchKeyword}%")
                        ->orWhereLike('last_name', "%{$searchKeyword}%");
                });
            })->with(['permitSettings'])->limit(50)->get();

            return $this->responseSuccess([
                'data' => $invitees->map(function ($invitee) {
                    $canInvite = (! $invitee->permitSettings->group_invites->nobody());

                    return [
                        'id' => $invitee->id,
                        'meta' => [
                            'can_invite' => $canInvite
                        ],
                        'relations' => [
                            'user' => UserPreviewResource::make($invitee, [
                                'last_active' => [
                                    'online' => $invitee->isOnline(),
                                    'timestamp' => $invitee->getLastActive()->getTimestamp(),
                                    'time_ago' => $invitee->getLastActive()->getTimeAgo()
                                ]
                            ])
                        ]
                    ];
                })
            ]);
        }

        return $this->responseResourceNotFoundError('Chat', $request->chat_id);
    }

    public function showGroup(string $chatId)
    {
        if(Str::isUuid($chatId)) {
            $chatData = $this->findGroupWithChatId($chatId);

            if($chatData) {
                return $this->responseSuccess([
                    'data' => GroupResource::make($chatData->group)
                ]);
            }
        }

        return $this->responseResourceNotFoundError('Chat', $chatId);
    }

    public function editGroup(string $chatId)
    {
        if(Str::isUuid($chatId)) {
            $chatData = $this->findGroupWithChatId($chatId);

            if($chatData) {
                try {
                    $this->authorize('edit', $chatData->group);
                                        
                    return $this->responseSuccess([
                        'data' => EditGroupResource::make($chatData->group)
                    ]);
                    
                } catch (AuthorizationException $e) {
                    return $this->responseUnauthorizedError();
                }
            }
        }

        return $this->responseResourceNotFoundError('Chat', $chatId);
    }

    public function updateGroup(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'visibility' => ['required', 'boolean'],
            'chat_id' => ['required', 'string','uuid'],
            'description' => ['nullable', 'string', 'max:240'],
        ]);

        $chatData = $this->findGroupWithChatId($request->chat_id);

        if(! $chatData) {
            return $this->responseResourceNotFoundError('Chat', $request->chat_id);
        }

        $groupData = $chatData->group;

        $groupData->update([
            'name' => e($request->name),
            'visibility' => $request->visibility ? GroupVisibility::PUBLIC : GroupVisibility::HIDDEN,
            'status' => GroupStatus::ACTIVE,
            'description' => empty($request->description) ? '' : $request->description
        ]);

        $chatData->update([
            'last_activity' => now(),
        ]);

        return $this->responseSuccess([
            'data' => EditGroupResource::make($groupData->refresh())
        ]);
    }

    public function updateGroupAvatar(Request $request)
    {
        $request->validate([
            'avatar_file' => [
                'required',
                'image',
                config('user.validation.avatar.mimes'),
                config('user.validation.avatar.max')
            ],
            'chat_id' => ['required', 'string','uuid']
        ]);

        $chatData = $this->findGroupWithChatId($request->chat_id);

        if(! $chatData) {
            return $this->responseResourceNotFoundError('Chat', $request->chat_id);
        }

        $groupData = $chatData->group;

        $imageUploadService = app(ImageUploadService::class);

        $fileDeleteService = app(FileDeleteService::class);

        $imageData = $imageUploadService->load($request->avatar_file->getRealPath())
            ->setStorageDisk(static_storage_disk())
            ->setNamespace('uploads/groups/avatars')
            ->crop(config('user.avatar_config.crop_size'), config('user.avatar_config.crop_size'))
            ->compress(config('user.avatar_config.compress_rate'))
            ->upload();

        if(! empty($imageData)) {
            if(! $groupData->hasDefaultAvatar() && ! empty($groupData->avatar)) {
                $fileDeleteService->setStorageDisk(static_storage_disk())->deleteFile($groupData->avatar);
            }

            $groupData->update([
                'avatar' => $imageData['image_path']
            ]);

            return $this->responseSuccess([
                'data' => [
                    'avatar_url' => storage_url($imageData['image_path'], static_storage_disk())
                ]
            ]);
        }

        return $this->responseError([
            'message' => 'Failed to update group avatar'
        ]);
    }

    private function findGroupWithChatId(string $chatId)
    {
        return Chat::where('chat_id', $chatId)->where('type', ChatType::GROUP)
            ->whereHas('group')->with('group')->first();
    }

    public function getGroupParticipants(string $chatId)
    {
        if(Str::isUuid($chatId)) {
            $chatData = $this->findGroupWithChatId($chatId);

            if($chatData) {
                // TODO: Remove this limit and make it loadable by cursor id from client side.
                // Improve it in future.

                $chatParticipants = $chatData->participants()->with('user')->take(2000)->get();

                $chatParticipants->each(function($p) use ($chatData) {
                    if($p->user->id === $chatData->group->user_id) {
                        $p->is_group_admin = true;
                    }
                });
    
                return $this->responseSuccess([
                    'data' => ParticipantCollection::make($chatParticipants)
                ]);
            }
        }

        return $this->responseResourceNotFoundError('Chat', $chatId);
    }

    public function getGroupRecentJoins(string $chatId)
    {
        if(Str::isUuid($chatId)) {
            $chatData = $this->findGroupWithChatId($chatId);

            if($chatData) {
                $chatParticipants = $chatData->participants()->with('user')->take(16)
                ->orderBy('joined_at', 'desc')->get();
    
                return $this->responseSuccess([
                    'data' => ParticipantCollection::make($chatParticipants)
                ]);
            }
        }

        return $this->responseResourceNotFoundError('Chat', $chatId);
    }
}
