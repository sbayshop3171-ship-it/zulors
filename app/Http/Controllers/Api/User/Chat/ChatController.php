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

namespace App\Http\Controllers\Api\User\Chat;

use App\Actions\Chat\MessageGlobalDeleteAction;
use App\Actions\Chat\MessagesLocalDeleteAction;
use App\Constants\Relationship;
use App\Database\Configs\Table;
use App\Enums\Chat\ChatType;
use App\Enums\Chat\MessageType;
use App\Enums\User\PrivacyPermit;
use App\Events\User\Chat\MessageDeletedEvent;
use App\Events\User\Chat\MessageMediaReadyEvent;
use App\Events\User\Chat\MessageReadEvent;
use App\Events\User\Chat\MessageReceivedEvent;
use App\Events\User\Chat\MessageReactionsUpdatedEvent;
use App\Http\Controllers\Controller;
use App\Http\Resources\User\Chat\ChatCollection;
use App\Http\Resources\User\Chat\ChatResource;
use App\Http\Resources\User\Chat\MessageCollection;
use App\Http\Resources\User\Chat\MessageResource;
use App\Http\Resources\User\Chat\ParticipantCollection;
use App\Http\Resources\User\Chat\RequestCollection;
use App\Http\Resources\User\Overview\UserOverviewResource;
use App\Http\Resources\User\Timeline\ReactionCollection;
use App\Http\Resources\User\User\UserPreviewResource;
use App\Models\Chat;
use App\Models\ChatInvite;
use App\Models\HiddenChat;
use App\Models\JobListing;
use App\Models\MessengerSearchRecent;
use App\Models\Message;
use App\Models\Product;
use App\Models\User;
use App\Notifications\User\Chat\MessageReceivedNotification;
use App\Rules\X\XRule;
use App\Services\Reaction\ReactionService;
use App\Services\Relations\BlockService;
use App\Services\Relations\FollowService;
use App\Support\Num;
use App\Traits\Http\Api\SupportsApiResponses;
use App\Traits\Http\Controllers\Api\User\Chat\WithMediaUpload;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class ChatController extends Controller
{
    use SupportsApiResponses,
        WithMediaUpload,
        AuthorizesRequests;

    private function loadMessageRealtimeRelations(Message $messageData): Message
    {
        return $messageData->load([
            'reactions',
            'media',
            'participant',
            'user:id,first_name,last_name,username,avatar,verified',
            'parent.user:id,first_name,last_name,username,avatar,verified',
            'parent.participant',
            'parent.media',
            'parent.linkSnapshot',
            'linkSnapshot'
        ]);
    }

    private function getUserPresencePayload(?User $userData): ?array
    {
        if(empty($userData) || empty($userData->last_active)) {
            return null;
        }

        $lastActiveAt = Carbon::parse($userData->last_active);
        $minutesAgo = max(0, (int) $lastActiveAt->diffInMinutes(now()));
        $isOnline = $userData->isOnline();

        return [
            'is_online' => $isOnline,
            'recent' => (! $isOnline && $minutesAgo < 60),
            'minutes_ago' => $minutesAgo,
            'short_label' => ($isOnline ? null : "{$minutesAgo}m"),
            'last_seen_at' => [
                'raw' => $userData->getLastActive()->getTimestamp(),
                'formatted' => $userData->getLastActive()->getCalendar(),
                'time_ago' => $userData->getLastActive()->getTimeAgo()
            ]
        ];
    }

    private function findOwnedAudioMessage(int $messageId): ?Message
    {
        return Message::query()
            ->where('id', $messageId)
            ->where('user_id', me()->id)
            ->with(['chat', 'media'])
            ->whereHas('chat.participants', function ($query) {
                $query->where('user_id', me()->id);
            })
            ->first();
    }

    private function isPendingAudioMessage(?Message $messageData): bool
    {
        return $messageData instanceof Message
            && $messageData->type?->isAudio()
            && ! $messageData->is_deleted
            && $messageData->media
            && ! $messageData->media->status->isProcessed();
    }

    private function notifyChatParticipants(Message $messageData, $otherParticipants): void
    {
        try {
            event(new MessageReceivedEvent($messageData));

            $otherParticipants->each(function ($participantData) use ($messageData) {
                $participantData->user->notify(new MessageReceivedNotification($messageData));
            });
        } catch (Throwable $th) {
            // Pass
        }
    }

    private function touchChatAfterMessage(Chat $chatData): void
    {
        $chatData->update([
            'last_activity' => now()
        ]);

        if ($chatData->type->isDirect()) {
            HiddenChat::where('chat_id', $chatData->id)->delete();
        }
    }

    public function getChats()
    {
        $chatsHistory = Chat::chatsHistory()
            ->withUnreadMessagesCountForUser(me()->id)
            ->with(['interlocutor.user', 'group', 'lastMessage'])
            ->latest('last_activity')
            ->get();

        return $this->responseSuccess([
            'data' => ChatCollection::make($chatsHistory)
        ]);
    }

    public function getArchive()
    {
        $chatsHistory = Chat::chatsArchive()
            ->withUnreadMessagesCountForUser(me()->id)
            ->with(['interlocutor.user', 'group', 'lastMessage'])
            ->latest('last_activity')
            ->get();

        return $this->responseSuccess([
            'data' => ChatCollection::make($chatsHistory)
        ]);
    }

    public function getChatRequests()
    {
        $chatRequests = ChatInvite::pending()->where('receiver_id', me()->id)->with(['sender', 'chat.group'])->get();

        return $this->responseSuccess([
            'data' => RequestCollection::make($chatRequests)
        ]);
    }

    public function getChatRequestsCount()
    {
        $count = ChatInvite::pending()->where('receiver_id', me()->id)->count();

        return $this->responseSuccess([
            'data' => [
                'count' => $count,
            ]
        ]);
    }

    public function getUnreadCount()
    {
        $userId = me()->id;
        $unreadCount = Message::query()
            ->join(Table::CHAT_PARTICIPANTS, Table::CHAT_PARTICIPANTS . '.chat_id', '=', Table::MESSAGES . '.chat_id')
            ->where(Table::CHAT_PARTICIPANTS . '.user_id', $userId)
            ->where(Table::MESSAGES . '.user_id', '!=', $userId)
            ->whereRaw(Table::MESSAGES . '.id > COALESCE(' . Table::CHAT_PARTICIPANTS . '.last_read_message_id, 0)')
            ->whereNotIn(Table::MESSAGES . '.id', function ($subQuery) use ($userId) {
                $subQuery->select('message_id')->from(Table::HIDDEN_MESSAGES)->where('user_id', $userId);
            })
            ->whereNotIn(Table::MESSAGES . '.chat_id', function ($subQuery) use ($userId) {
                $subQuery->select('chat_id')->from(Table::HIDDEN_CHATS)->where('user_id', $userId);
            })
            ->count(Table::MESSAGES . '.id');

        return $this->responseSuccess([
            'data' => [
                'formatted' => Num::abbreviate($unreadCount),
                'raw' => $unreadCount
            ]
        ]);
    }

    public function markAsRead(string $chatId)
    {
        if(Str::isUuid($chatId)) {

            $chatData = Chat::participatedChats()->with(['interlocutor.user', 'group'])->where('chat_id', $chatId)->first();

            if($chatData) {
                $userParticipant = $chatData->participants()->where('user_id', me()->id)->first();
                $lastMessageData = $chatData->messages()->latest()->first();
                $statusUpdated = false;

                if($lastMessageData && $userParticipant) {
                    if($userParticipant->last_read_message_id < $lastMessageData->id) {
                        $statusUpdated = true;
                        $userParticipant->update([
                            'last_read_message_id' => $lastMessageData->id,
                            'last_read_at' => now()
                        ]);

                        try {
                            event(new MessageReadEvent([
                                'chat_uuid' => $chatId,
                                'user_id' => me()->id,
                                'last_read_message_id' => $lastMessageData->id
                            ]));
                        } catch (Throwable $th) {
                            // Pass
                        }
                    }
                }

                return $this->responseSuccess([
                    'data' => [
                        'status_updated' => $statusUpdated
                    ]
                ]);
            }
        }

        return $this->responseResourceNotFoundError('Chat', $chatId);
    }

    public function getChatParticipants(string $chatId)
    {
        if(Str::isUuid($chatId)) {
            $chatData = Chat::participatedChats()->where('chat_id', $chatId)->first();

            if($chatData) {
                $chatParticipants = $chatData->participants()->with('user:id,first_name,last_name,username,avatar,caption,last_active,verified')->take(7)->get();

                return $this->responseSuccess([
                    'data' => ParticipantCollection::make($chatParticipants)
                ]);
            }
        }

        return $this->responseResourceNotFoundError('Chat', $chatId);
    }

    public function getChatMessages(Request $request, string $chatId)
    {
        if(Str::isUuid($chatId)) {
            $chatData = Chat::participatedChats()->where('chat_id', $chatId)->first();

            if($chatData) {
                $limit = min(50, max(1, $request->integer('limit', 30)));
                $beforeId = $request->integer('before_id');
                $chatMessagesQuery = $chatData->messages()->excludeDeleted()->with([
                    'reactions',
                    'media',
                    'participant',
                    'user:id,first_name,last_name,username,avatar,verified',
                    'parent.user:id,first_name,last_name,username,avatar,verified',
                    'parent.participant',
                    'parent.media',
                    'parent.linkSnapshot',
                    'linkSnapshot'
                ]);

                if($beforeId > 0) {
                    $chatMessagesQuery->where('id', '<', $beforeId);
                }

                $fetchedMessages = $chatMessagesQuery->latest('id')->take($limit + 1)->get();
                $hasMoreMessages = $fetchedMessages->count() > $limit;
                $chatMessages = $fetchedMessages->take($limit)->reverse()->values();

                return $this->responseSuccess([
                    'data' => MessageCollection::make($chatMessages),
                    'meta' => [
                        'pagination' => [
                            'limit' => $limit,
                            'has_more' => $hasMoreMessages,
                            'oldest_id' => $chatMessages->first()?->id,
                            'next_before_id' => $hasMoreMessages ? $chatMessages->first()?->id : null
                        ]
                    ]
                ]);
            }
        }

        return $this->responseResourceNotFoundError('Chat', $chatId);
    }

    public function createChat(Request $request)
    {
        $userId = $request->integer('user_id');

        $userData = User::active()->excludeSelf()->find($userId);

        if(empty($userData)) {
            return $this->responseResourceNotFoundError('User', $userId);
        }
        else if(! $this->canStartDirectChat($userData)) {
            return $this->responseDirectChatNotAllowedError();
        }
        else {
            $chatData = $this->initiateChat($userId)->load(['interlocutor.user', 'group', 'lastMessage']);

            return $this->responseSuccess([
                'data' => [
                    'chat_id' => $chatData->chat_id,
                    'chat' => ChatResource::make($chatData)
                ]
            ]);
        }
    }

    public function launchChat(Request $request)
    {
        $userId = $request->integer('user_id');

        $userData = User::active()->excludeSelf()->find($userId);

        if(empty($userData)) {
            return $this->responseResourceNotFoundError('User', $userId);
        }
        else if(! $this->canStartDirectChat($userData)) {
            return $this->responseDirectChatNotAllowedError();
        }
        else {
            $chatData = $this->initiateChat($userId);

            return $this->responseSuccess([
                'data' => [
                    'interlocutor' => UserOverviewResource::make($userData),
                    'chat_id' => $chatData->chat_id,
                    'validation_rules' => [
                        'content' => config('chat.message.validation.content')
                    ]
                ]
            ]);
        }

    }

    public function getSearchBootstrap()
    {
        $recentIds = MessengerSearchRecent::where('user_id', me()->id)->pluck('target_user_id')->all();

        return $this->responseSuccess([
            'data' => [
                'recents' => $this->getSearchRecents(),
                'suggestions' => $this->getSearchSuggestions(10, $recentIds)
            ]
        ]);
    }

    public function search(Request $request)
    {
        $searchQuery = Str::squish(strval($request->get('q', '')));

        if(empty($searchQuery)) {
            return $this->getSearchBootstrap();
        }

        $chatResults = $this->getSearchChatResults($searchQuery);
        $chatUserIds = $chatResults->filter(function($chatData) {
            return ($chatData->type->isDirect() && $chatData->interlocutor && $chatData->interlocutor->user);
        })->map(function($chatData) {
            return $chatData->interlocutor->user->id;
        })->all();

        return $this->responseSuccess([
            'data' => [
                'query' => $searchQuery,
                'chats' => ChatCollection::make($chatResults),
                'users' => $this->mapSearchUsers($this->getSearchUserResults($searchQuery, $chatUserIds))
            ]
        ]);
    }

    public function storeSearchRecent(Request $request)
    {
        $validator = Validator::make([
            'user_id' => $request->integer('user_id')
        ], [
            'user_id' => ['required', 'integer', Rule::exists(Table::USERS, 'id')]
        ]);

        if($validator->fails()) {
            return $this->throwValidationError($validator);
        }

        $userId = $request->integer('user_id');
        $userData = $this->directSearchableUsersQuery()->find($userId);

        if(empty($userData) || (! $this->canStartDirectChat($userData))) {
            return $this->responseDirectChatNotAllowedError();
        }

        MessengerSearchRecent::updateOrCreate([
            'user_id' => me()->id,
            'target_user_id' => $userId
        ], [
            'searched_at' => now()
        ]);

        $this->trimSearchRecents();

        return $this->responseSuccess([
            'data' => [
                'recents' => $this->getSearchRecents()
            ]
        ]);
    }

    public function deleteSearchRecent(int $userId)
    {
        MessengerSearchRecent::where('user_id', me()->id)
            ->where('target_user_id', $userId)
            ->delete();

        return $this->responseSuccess([
            'data' => [
                'recents' => $this->getSearchRecents()
            ]
        ]);
    }

    public function clearSearchRecents()
    {
        MessengerSearchRecent::where('user_id', me()->id)->delete();

        return $this->responseSuccess([
            'data' => [
                'recents' => []
            ]
        ]);
    }

    public function getChatData(Request $request, string $chatId)
    {
        if(Str::isUuid($chatId)) {
            $chatData = Chat::participatedChats()->where('chat_id', $chatId)->first();

            if($chatData) {
                $participantsCount = $chatData->participants()->count();
                $isParticipant = $chatData->isParticipant(me()->id);

                $chatInfo = [
                    'type' => $chatData->type->value,
                    'is_group' => $chatData->type->isGroup(),
                    'chat_id' => $chatData->chat_id,
                    'meta' => [
                        'is_participant' => $isParticipant,
                        'is_archived' => $chatData->isArchived(me()->id)
                    ],
                    'date' => [
                        'timestamp' => $chatData->created_at->getTimestamp(),
                        'iso' => $chatData->created_at->getIso(),
                    ],
                    'chat_info' => [
                        'participants_count' => [
                            'raw' => $participantsCount,
                            'formatted' => Num::abbreviate($participantsCount)
                        ],
                        'verified' => false,
                    ],
                    'relations' => [
                        'participants' => []
                    ]
                ];

                // Check if the user is a participant and the chat is a direct chat.

                if ($chatData->type == ChatType::DIRECT) {
                    $interlocutorData = $chatData->interlocutor;

                    $interlocutorData = (empty($interlocutorData)) ? null : $interlocutorData->user;

                    $chatInfo['relations']['participants'] = $chatData
                        ->participants()
                        ->whereNot('user_id', me()->id)
                        ->select([
                            'user_id',
                            'last_read_message_id',
                            'last_read_at',
                        ])->get()->toArray();

                    // TODO: Add deleted user support.

                    $blockedAny = $this->isBlockedAny($interlocutorData);

                    $chatInfo['chat_info']['id'] = $interlocutorData->id;
                    $chatInfo['chat_info']['name'] = $interlocutorData->name;
                    $chatInfo['chat_info']['username'] = $interlocutorData->username;
                    $chatInfo['chat_info']['avatar_url'] = ($blockedAny) ? asset(config('user.avatar')) : $interlocutorData->avatar_url;
                    $chatInfo['chat_info']['description'] = ($blockedAny) ? null : $interlocutorData->bio;
                    $chatInfo['chat_info']['verified'] = $interlocutorData->isVerified();

                    $chatInfo['chat_info']['followers_count'] = ($blockedAny) ? null : [
                        'raw' => $interlocutorData->followers_count,
                        'formatted' => Num::abbreviate($interlocutorData->followers_count)
                    ];

                    $chatInfo['chat_info']['last_active'] = ($blockedAny) ? null : [
                        'raw' => $interlocutorData->getLastActive()->getTimestamp(),
                        'formatted' => $interlocutorData->getLastActive()->getCalendar()
                    ];

                    $chatInfo['chat_info']['presence'] = ($blockedAny) ? null : $this->getUserPresencePayload($interlocutorData);

                    $chatInfo['chat_info']['meta'] = [
                        'relationship' => [
                            Relationship::FOLLOW_GROUP => [
                                Relationship::FOLLOWED_BY => ($blockedAny) ? false : (new FollowService($interlocutorData, me()))->isFollowing(),
                            ],
                            Relationship::BLOCK_GROUP => [
                                Relationship::BLOCKING => $blockedAny
                            ],
                        ]
                    ];
                }

                else if ($chatData->type == ChatType::GROUP) {
                    $groupData = $chatData->group;

                    $chatInfo['chat_info']['id'] = $groupData->id;
                    $chatInfo['chat_info']['name'] = $groupData->name;
                    $chatInfo['chat_info']['avatar_url'] = $groupData->avatar_url;
                    $chatInfo['chat_info']['verified'] = $groupData->isVerified();
                    $chatInfo['chat_info']['description'] = $groupData->description;
                }

                return $this->responseSuccess([
                    'data' => $chatInfo
                ]);
            }
        }

        return $this->responseResourceNotFoundError('Chat', $chatId);
    }

    public function sendMessage(Request $request)
    {
        $validator = Validator::make([
            'chat_id' => $request->get('chat_id'),
            'content' => $request->get('content'),
            'parent_id' => $request->get('parent_id'),
            'client_uid' => $request->get('client_uid'),
            'media_type' => $request->get('media_type'),
            'message_type' => $request->get('message_type'),
            'media' => $request->file('media'),
            'media_duration' => $request->get('media_duration'),
        ], [
            'chat_id' => ['required', 'uuid'],
            'parent_id' => ['nullable', 'integer'],
            'client_uid' => ['nullable', 'string', 'max:100'],
            'content' => ['nullable', 'required_without:media', 'string', 'min:1', XRule::join('max', config('chat.message.validation.content.max'))],
            'media_type' => ['nullable', 'required_with:media', 'string', Rule::in(config('chat.validation.message.media_type.types'))],
            'message_type' => ['nullable', 'string', Rule::in(config('chat.validation.message.message_type.types'))],
            // TODO: Add validation types from config not hardcoded.
            'media_duration' => ['nullable', 'requiredIf:media_type,video,audio', 'integer', 'min:1'],
            'media' => ['nullable', 'required_without:content', 'file',
                XRule::join('mimes', config('chat.validation.message.media.mimes')),
                XRule::join('mimetypes', config('chat.validation.message.media.mimetypes')),
                XRule::join('max', config('chat.validation.message.media.max'))
            ],
        ]);

        if($validator->passes()) {
            $chatId = $request->input('chat_id');
            $messageContent = $request->input('content');
            $parentId = $request->integer('parent_id');
            $chatData = Chat::participatedChats()->where('chat_id', $chatId)->first();

            if($chatData) {
                $participantData = $chatData->participants()->where('user_id', me()->id)->first();
                $otherParticipants = $chatData->participants()->whereNot('user_id', me()->id)->get();
                $messageContentLanguage = '';

                if($messageContent) {
                    $messageContentLanguage = detect_text_language($messageContent);
                }

                $messageInsertData = [
                    'content' => e($messageContent),
                    'user_id' => me()->id,
                    'chat_uuid' => $chatId,
                    'participant_id' => $participantData->id,
                    'text_language' => $messageContentLanguage
                ];

                if($request->input('message_type') === MessageType::LOCATION->value) {
                    $messageInsertData['type'] = MessageType::LOCATION;
                }

                if($parentId) {
                    $replayableMessageExists = $chatData->messages()->where('id', $parentId)->exists();

                    if(empty($replayableMessageExists)) {
                        return $this->responseResourceNotFoundError('Message', $parentId);
                    }

                    $messageInsertData['parent_id'] = $parentId;
                }

                $messageData = $chatData->messages()->create($messageInsertData);

                $participantData->update([
                    'last_read_message_id' => $messageData->id,
                    'last_read_at' => now()
                ]);

                // Handle media upload.
                if($request->hasFile('media')) {
                    $mediaDuration = $request->input('media_duration') ?? 0;
                    $mediaType = $request->input('media_type');
                    $mediaFile = $request->file('media');

                    $this->uploadMedia($messageData, $mediaFile, $mediaType, $mediaDuration);

                    $messageData->load('media');
                }

                $messageData = $this->loadMessageRealtimeRelations($messageData);
                $clientUid = $request->input('client_uid');

                if(! empty($clientUid)) {
                    $messageData->setAttribute('client_uid', $clientUid);
                }

                try {
                    event(new MessageReceivedEvent($messageData, $clientUid));

                    $otherParticipants->each(function ($participantData) use ($messageData) {
                        $participantData->user->notify(new MessageReceivedNotification($messageData));
                    });
                } catch (Throwable $th) {
                    // Pass
                }

                $chatData->update([
                    'last_activity' => now()
                ]);

                if ($chatData->type->isDirect()) {
                    HiddenChat::where('chat_id', $chatData->id)->delete();
                }

                return $this->responseSuccess([
                    'data' => MessageResource::make($messageData)
                ]);
            }

            return $this->responseResourceNotFoundError('Chat', $chatId);
        }
        else{
            return $this->throwValidationError($validator);
        }
    }

    public function initAudioMessage(Request $request)
    {
        $validator = Validator::make([
            'chat_id' => $request->input('chat_id'),
            'parent_id' => $request->input('parent_id'),
            'duration_seconds' => $request->input('duration_seconds'),
            'extension' => $request->input('extension'),
            'mime_type' => $request->input('mime_type'),
            'file_name' => $request->input('file_name'),
        ], [
            'chat_id' => ['required', 'uuid'],
            'parent_id' => ['nullable', 'integer'],
            'duration_seconds' => ['required', 'integer', 'min:1', 'max:120'],
            'extension' => ['required', 'string', Rule::in(['m4a', 'mp4', 'mp3', 'mpeg', 'wav', 'wave', 'aac', 'ogg', 'webm'])],
            'mime_type' => ['required', 'string', 'max:120'],
            'file_name' => ['nullable', 'string', 'max:255'],
        ]);

        if(! $validator->passes()) {
            return $this->throwValidationError($validator);
        }

        $chatId = $request->input('chat_id');
        $parentId = $request->integer('parent_id');
        $chatData = Chat::participatedChats()->where('chat_id', $chatId)->first();

        if(empty($chatData)) {
            return $this->responseResourceNotFoundError('Chat', $chatId);
        }

        $participantData = $chatData->participants()->where('user_id', me()->id)->first();
        $otherParticipants = $chatData->participants()->whereNot('user_id', me()->id)->get();

        $messageInsertData = [
            'content' => null,
            'user_id' => me()->id,
            'chat_uuid' => $chatId,
            'participant_id' => $participantData->id,
            'text_language' => '',
            'type' => MessageType::AUDIO,
        ];

        if($parentId) {
            $replyableMessageExists = $chatData->messages()->where('id', $parentId)->exists();

            if(empty($replyableMessageExists)) {
                return $this->responseResourceNotFoundError('Message', $parentId);
            }

            $messageInsertData['parent_id'] = $parentId;
        }

        $messageData = $chatData->messages()->create($messageInsertData);

        $participantData->update([
            'last_read_message_id' => $messageData->id,
            'last_read_at' => now()
        ]);

        $this->createPendingAudioMedia($messageData, [
            'duration_seconds' => $request->integer('duration_seconds'),
            'extension' => $request->input('extension'),
            'mime_type' => $request->input('mime_type'),
            'file_name' => $request->input('file_name'),
        ]);

        $messageData = $this->loadMessageRealtimeRelations($messageData->fresh());

        $this->notifyChatParticipants($messageData, $otherParticipants);
        $this->touchChatAfterMessage($chatData);

        return $this->responseSuccess([
            'data' => MessageResource::make($messageData)
        ], Response::HTTP_CREATED);
    }

    public function uploadAudioMessage(Request $request, int $messageId)
    {
        $validator = Validator::make([
            'audio' => $request->file('audio'),
        ], [
            'audio' => ['required', 'file',
                XRule::join('mimes', 'm4a,mp4,mp3,wav,aac,ogg,webm,mpeg'),
                XRule::join('mimetypes', 'audio/aac,audio/mp4,audio/mpeg,audio/mp3,audio/ogg,audio/webm,audio/wav,audio/x-m4a,audio/x-wav,video/webm'),
                XRule::join('max', config('chat.validation.message.media.max'))
            ],
        ]);

        if(! $validator->passes()) {
            return $this->throwValidationError($validator);
        }

        $messageData = $this->findOwnedAudioMessage($messageId);

        if(empty($messageData)) {
            return $this->responseResourceNotFoundError('Message', $messageId);
        }

        if(! $this->isPendingAudioMessage($messageData)) {
            return $this->responseError([
                'message' => 'This voice note can no longer accept an upload.',
                'errors' => [
                    'message' => 'Voice note upload is no longer pending.'
                ]
            ], Response::HTTP_CONFLICT);
        }

        if(data_get($messageData->media->metadata, 'processing_state') !== 'waiting_for_upload') {
            return $this->responseError([
                'message' => 'This voice note upload has already started.',
                'errors' => [
                    'message' => 'Voice note upload is not awaiting a new file.'
                ]
            ], Response::HTTP_CONFLICT);
        }

        $isQueued = $this->uploadPendingAudioMedia($messageData, $request->file('audio'));
        $messageData = $this->loadMessageRealtimeRelations($messageData->fresh());

        if(! $isQueued) {
            event(new MessageMediaReadyEvent($messageData));
        }

        return $this->responseSuccess([
            'data' => MessageResource::make($messageData)
        ], $isQueued ? Response::HTTP_ACCEPTED : Response::HTTP_OK);
    }

    public function failAudioMessage(int $messageId)
    {
        $messageData = $this->findOwnedAudioMessage($messageId);

        if(empty($messageData)) {
            return $this->responseResourceNotFoundError('Message', $messageId);
        }

        if(! $this->isPendingAudioMessage($messageData)) {
            return $this->responseError([
                'message' => 'This voice note is no longer pending cleanup.',
                'errors' => [
                    'message' => 'Voice note cleanup is only allowed while media is still pending.'
                ]
            ], Response::HTTP_CONFLICT);
        }

        (new MessageGlobalDeleteAction($messageData->load('media')))->execute();

        event(new MessageDeletedEvent($messageData->id, $messageData->chat_uuid));

        return response()->noContent();
    }

    public function launcherSendMessage(Request $request)
    {
        $validator = Validator::make([
            'chat_id' => $request->input('chat_id'),
            'content' => $request->input('content'),
            'payload' => $request->input('payload')
        ], [
            'chat_id' => ['required', 'uuid'],
            'content' => ['required', 'string', 'min:1', XRule::join('max', config('chat.message.validation.content.max'))],
            'payload' => ['nullable', 'array']
        ]);

        if($validator->passes()) {
            $chatId = $request->input('chat_id');
            $messageContent = $request->input('content');
            $payload = $request->input('payload');

            $chatData = Chat::participatedChats()->where('chat_id', $chatId)->first();

            if($chatData) {
                $participantData = $chatData->participants()->where('user_id', me()->id)->first();

                $messageInsertData = [
                    'content' => e($messageContent),
                    'user_id' => me()->id,
                    'chat_uuid' => $chatId,
                    'participant_id' => $participantData->id
                ];

                $messageData = $chatData->messages()->create($messageInsertData);

                $chatData->update([
                    'last_activity' => now()
                ]);

                if ($chatData->type->isDirect()) {
                    HiddenChat::where('chat_id', $chatData->id)->delete();
                }

                if($payload) {
                    $resourceId = data_get_integer($payload, 'id', 0);
                    $resourceType = data_get($payload, 'type', null);

                    if($resourceId) {
                        if($resourceType == 'product') {
                            try {
                                $productData = Product::listable()->withRelations()->find($resourceId);

                                if($productData) {
                                    $displayPrice = $productData->hasDiscount()
                                        ? $productData->formatted_sale_price
                                        : $productData->formatted_price;

                                    $messageData->linkSnapshot()->create([
                                        'title' => Str::limit($productData->title, 250),
                                        'description' => null,
                                        'url' => $productData->url,
                                        'metadata' => [
                                            'entity' => 'product',
                                            'is_fallback' => false,
                                            'preview_image_base64' => null,
                                            'preview_image_url' => $productData->preview_image_url,
                                            'seller' => [
                                                'id' => $productData->user->id,
                                                'name' => $productData->user->name,
                                                'username' => $productData->user->username,
                                                'avatar_url' => $productData->user->avatar_url,
                                                'verified' => $productData->user->isVerified(),
                                            ],
                                            'price' => [
                                                'raw' => $productData->hasDiscount() ? $productData->sale_price : $productData->price,
                                                'formatted' => $displayPrice,
                                            ],
                                        ]
                                    ]);
                                }
                            } catch (Throwable $e) {
                                // Pass
                            }
                        }

                        else if($resourceType == 'job') {
                            try {
                                $jobData = JobListing::listable()->find($resourceId);

                                if($jobData) {
                                    $jobData->increment('applications_count');
                                    $jobData->increment('views_count');

                                    $messageData->linkSnapshot()->create([
                                        'title' => Str::limit($jobData->title, 250),
                                        'description' => Str::limit($jobData->overview, 250),
                                        'url' => $jobData->url,
                                        'metadata' => [
                                            'is_fallback' => false,
                                            'preview_image_base64' => null
                                        ]
                                    ]);
                                }
                            } catch (Throwable $e) {
                                // Pass
                            }
                        }
                    }
                }

                $messageData = $this->loadMessageRealtimeRelations($messageData);

                try {
                    event(new MessageReceivedEvent($messageData));
                } catch (Throwable $th) {
                    // Pass
                }

                return $this->responseSuccess([
                    'data' => MessageResource::make($messageData)
                ]);
            }

            return $this->responseResourceNotFoundError('Chat', $chatId);
        }
        else{
            return $this->throwValidationError($validator);
        }
    }

    public function addReaction(Request $request, ReactionService $reactionService)
    {
        $request->validate([
            'message_id' => ['required', 'integer'],
            'unified_id' => ['required', 'string', 'min:4', 'max:32']
        ]);

        $reactionUnifiedId = $request->get('unified_id');
        $messageId = $request->get('message_id');

        try {
            $messageData = Message::find($messageId);

            if ($messageData) {
                $isReactionAdded = $reactionService
                    ->setUserId(me()->id)
                    ->setReactable($messageData)
                    ->setUnifiable(strtolower($reactionUnifiedId))
                    ->handleReaction();

                $messageData->load('reactions');

                try {
                    event(new MessageReactionsUpdatedEvent($messageData, me()->id));
                } catch (Throwable $th) {
                    // Pass
                }

                return $this->responseSuccess([
                    'data' => ReactionCollection::make($messageData->reactions)
                ]);
            }

            return $this->responseResourceNotFoundError('Message', $messageId);
        }

        catch (Exception $e) {
            return $this->responseError([
                'message' => $e->getMessage(),
                'errors' => [
                    $e->getMessage()
                ]
            ]);
        }
    }

    public function deleteMessage(Request $request)
    {
        $request->validate([
            'message_id' => ['required', 'integer']
        ]);

        $messageId = $request->get('message_id');

        $chatData = Chat::participatedChats()->whereHas('messages', function ($query) use ($messageId) {
            $query->where('id', $messageId);
        })->first();

        if(! empty($chatData)) {
            $messageData = $chatData->messages()->find($messageId);
        }

        if ($messageData) {
            try {
                $payload = $request->array('payload', []);
                $isGlobalDelete = ($messageData->isSender() && empty($payload['delete_for_all']) != true);

                if($isGlobalDelete) {
                    (new MessageGlobalDeleteAction($messageData))->execute();

                    event(new MessageDeletedEvent($messageData->id, $chatData->chat_id));
                }
                else {
                    (new MessagesLocalDeleteAction(new Collection([$messageData])))->execute();
                }

                $messageData->linkSnapshot()->delete();

                return $this->responseSuccess([
                    'data' => [
                        'is_global_delete' => $isGlobalDelete
                    ]
                ]);
            }
            catch (Throwable $th) {
                return $this->responseError([
                    'message' => $th->getMessage(),
                    'errors' => [
                        $th->getMessage()
                    ]
                ]);
            }
        }

        return $this->responseResourceNotFoundError('Message', $messageId);
    }

    public function clearConversation(string $chatId) {
        if(Str::isUuid($chatId)) {
            $chatData = Chat::participatedChats()->where('chat_id', $chatId)->first();

            if($chatData) {
                $batchSize = 1000;

                $chatData->messages()->excludeDeleted()->chunk($batchSize, function ($messagesChunkList) {
                    (new MessagesLocalDeleteAction($messagesChunkList))->execute();
                });

                return $this->responseSuccess([
                    'data' => null
                ]);
            }
        }

        return $this->responseResourceNotFoundError('Chat', $chatId);
    }

    public function archiveChat(string $chatId) {
        if(Str::isUuid($chatId)) {
            $chatData = Chat::participatedChats()->where('chat_id', $chatId)->first();

            if($chatData) {
                if(! $chatData->isArchived(me()->id)) {
                    $chatData->archiveChat(me()->id);
                }

                return $this->responseSuccess([
                    'data' => null
                ]);
            }
        }

        return $this->responseResourceNotFoundError('Chat', $chatId);
    }

    public function unarchiveChat(string $chatId) {
        if(Str::isUuid($chatId)) {
            $chatData = Chat::participatedChats()->where('chat_id', $chatId)->first();

            if($chatData) {
                $chatData->unarchiveChat(me()->id);

                return $this->responseSuccess([
                    'data' => null
                ]);
            }
        }

        return $this->responseResourceNotFoundError('Chat', $chatId);
    }

    public function deleteChat(string $chatId) {
        if(Str::isUuid($chatId)) {
            $chatData = Chat::participatedChats()->where('chat_id', $chatId)->first();

            if($chatData) {
                $batchSize = 1000;

                $chatData->messages()->excludeDeleted()->chunk($batchSize, function ($messagesChunkList) {
                    (new MessagesLocalDeleteAction($messagesChunkList))->execute();
                });

                HiddenChat::create([
                    'chat_id' => $chatData->id,
                    'user_id' => me()->id,
                    'type' => $chatData->type
                ]);

                return $this->responseSuccess([
                    'data' => null
                ]);
            }
        }

        return $this->responseResourceNotFoundError('Chat', $chatId);
    }

    private function initiateChat(int $userId)
    {
        $chatData = Chat::where('type', ChatType::DIRECT);
        $participantIds = [me()->id, $userId];

        foreach ($participantIds as $id) {
            $chatData->whereHas('participants', function ($query) use ($id) {
                $query->where('user_id', $id);
            });
        }

        $chatData = $chatData->first();

        if(empty($chatData)) {
            $chatData = Chat::create([
                'chat_id' => Str::uuid(),
                'type' => ChatType::DIRECT,
                'created_at' => now(),
                'last_activity' => null
            ]);


            $chatData->addParticipant(me()->id);
            $chatData->addParticipant($userId);
        }

        return $chatData;
    }

    private function getSearchRecents(int $limit = 8): array
    {
        $recents = MessengerSearchRecent::where('user_id', me()->id)
            ->whereHas('targetUser', function($query) {
                $this->applyDirectSearchableUserConstraints($query);
            })
            ->with(['targetUser'])
            ->latest('searched_at')
            ->take($limit)
            ->get();

        return $recents->map(function($recentData) {
            return $this->mapSearchUser($recentData->targetUser, [
                'recent_id' => $recentData->id,
                'searched_at' => [
                    'raw' => $recentData->searched_at->getTimestamp(),
                    'iso' => $recentData->searched_at->toIso8601String(),
                ]
            ]);
        })->all();
    }

    private function getSearchSuggestions(int $limit = 10, array $excludeIds = []): array
    {
        $users = $this->directSearchableUsersQuery()
            ->when(! empty($excludeIds), function($query) use ($excludeIds) {
                $query->whereNotIn('id', $excludeIds);
            })
            ->orderByDesc('verified')
            ->orderByDesc('followers_count')
            ->orderByDesc('last_active')
            ->take($limit)
            ->get();

        return $this->mapSearchUsers($users);
    }

    private function getSearchChatResults(string $searchQuery): Collection
    {
        return Chat::chatsHistory()
            ->with(['interlocutor.user', 'group', 'lastMessage'])
            ->where(function($query) use ($searchQuery) {
                $query->whereHas('participants', function($participantQuery) use ($searchQuery) {
                    $participantQuery->where('user_id', '!=', me()->id)->whereHas('user', function($userQuery) use ($searchQuery) {
                        $this->applyUserSearchQuery($userQuery, $searchQuery);
                    });
                })->orWhereHas('group', function($groupQuery) use ($searchQuery) {
                    $groupQuery->whereLike('name', "%{$searchQuery}%")
                        ->orWhereLike('description', "%{$searchQuery}%");
                });
            })
            ->latest('last_activity')
            ->take(10)
            ->get();
    }

    private function getSearchUserResults(string $searchQuery, array $excludeIds = []): Collection
    {
        return $this->directSearchableUsersQuery()
            ->when(! empty($excludeIds), function($query) use ($excludeIds) {
                $query->whereNotIn('id', $excludeIds);
            })
            ->where(function($query) use ($searchQuery) {
                $this->applyUserSearchQuery($query, $searchQuery);
            })
            ->orderByDesc('verified')
            ->orderByDesc('followers_count')
            ->take(12)
            ->get();
    }

    private function directSearchableUsersQuery()
    {
        $query = User::query();

        $this->applyDirectSearchableUserConstraints($query);

        return $query;
    }

    private function applyDirectSearchableUserConstraints($query): void
    {
        $query->active()
            ->excludeSelf()
            ->whereHas('permitSettings', function($settingsQuery) {
                $settingsQuery->where('direct_messages', '!=', PrivacyPermit::NOBODY->value);
            })
            ->where(function($privacyQuery) {
                $privacyQuery->whereDoesntHave('privacySettings')->orWhereHas('privacySettings', function($settingsQuery) {
                    $settingsQuery->where('search_privacy', false);
                });
            })
            ->whereNotIn('id', function($blockedQuery) {
                $blockedQuery->select('blocked_id')->from(Table::BLOCKS)->where('blocker_id', me()->id);
            })
            ->whereNotIn('id', function($blockingQuery) {
                $blockingQuery->select('blocker_id')->from(Table::BLOCKS)->where('blocked_id', me()->id);
            });
    }

    private function applyUserSearchQuery($query, string $searchQuery): void
    {
        $query->whereLike('username', "%{$searchQuery}%")
            ->orWhereLike('first_name', "%{$searchQuery}%")
            ->orWhereLike('last_name', "%{$searchQuery}%")
            ->orWhereLike('caption', "%{$searchQuery}%")
            ->orWhereLike('bio', "%{$searchQuery}%");
    }

    private function mapSearchUsers(Collection $users): array
    {
        return $users->map(function($userData) {
            return $this->mapSearchUser($userData);
        })->all();
    }

    private function mapSearchUser(User $userData, array $with = []): array
    {
        return (new UserPreviewResource($userData, array_merge($with, [
            'presence' => $this->getUserPresencePayload($userData)
        ])))->resolve();
    }

    private function trimSearchRecents(): void
    {
        $recentIds = MessengerSearchRecent::where('user_id', me()->id)
            ->latest('searched_at')
            ->pluck('id')
            ->slice(25);

        if($recentIds->isNotEmpty()) {
            MessengerSearchRecent::whereIn('id', $recentIds)->delete();
        }
    }

    private function canStartDirectChat(User $userData): bool
    {
        if($userData->permitSettings->direct_messages->nobody()) {
            return false;
        }

        return ! (new BlockService(me(), $userData))->blockedAny();
    }

    private function responseDirectChatNotAllowedError()
    {
        return $this->responseError([
            'message' => 'This user does not accept direct messages.'
        ], 403);
    }

    private function isBlockedAny(User $interlocutorData): bool
    {
        $blockService = new BlockService(me(), $interlocutorData);

        return $blockService->blockedAny();
    }
}
