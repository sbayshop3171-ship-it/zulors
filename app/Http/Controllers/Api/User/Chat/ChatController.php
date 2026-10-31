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
use App\Enums\Media\MediaStatus;
use App\Enums\Media\MediaType;
use App\Enums\User\PrivacyPermit;
use App\Events\User\Chat\MessageDeletedEvent;
use App\Events\User\Chat\MessageMediaReadyEvent;
use App\Events\User\Chat\MessageReadEvent;
use App\Events\User\Chat\MessageReceivedEvent;
use App\Events\User\Chat\MessageReactionsUpdatedEvent;
use App\Jobs\User\Chat\ProcessChatVideo;
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
use App\Services\Media\Cloudflare\R2DirectUploadService;
use App\Services\Chat\ForwardMessageMediaService;
use App\Services\Relations\BlockService;
use App\Services\Relations\FollowService;
use App\Support\Num;
use App\Traits\Http\Api\SupportsApiResponses;
use App\Traits\Http\Controllers\Api\User\Chat\WithMediaUpload;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class ChatController extends Controller
{
    use \App\Traits\Http\Controllers\Api\RequiresDirectVideoUploads;
    use \App\Traits\Http\Controllers\Api\SerializesMediaCompletion;
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

    private function findOwnedChatDirectVideoMedia(string $chatId, int $mediaId, string $uid)
    {
        return \App\Models\Media::query()
            ->where('id', $mediaId)
            ->where(function ($query) use ($uid) {
                $query->where('source_path', $uid)->orWhere('metadata->temp_path', $uid);
            })
            ->where('type', MediaType::VIDEO->value)
            ->whereHasMorph('mediaable', [Message::class], function($query) use ($chatId) {
                $query->where('user_id', me()->id)
                    ->where('chat_uuid', $chatId)
                    ->whereHas('chat.participants', function($participantQuery) {
                        $participantQuery->where('user_id', me()->id);
                    });
            })
            ->with('mediaable.chat')
            ->first();
    }

    private function maxDirectVideoBytes(): int
    {
        return max(1, (int) config('media.uploads.video.max_bytes', 8 * 1024 * 1024 * 1024));
    }

    private function maxDirectVideoDurationSeconds(): int
    {
        return max(1, (int) config('media.uploads.video.max_duration_seconds', 3600));
    }

    private function chatDirectVideoPresentationMetadata(Request $request): array
    {
        $width = $request->integer('width', 0);
        $height = $request->integer('height', 0);

        if($width < 1 || $height < 1) {
            return [
                'is_portrait' => false,
            ];
        }

        return [
            'dimensions' => [
                'width' => $width,
                'height' => $height,
            ],
            'aspect_ratio' => round($width / $height, 6),
            'is_portrait' => $width < $height,
        ];
    }

    private function chatDirectVideoPublicDisk(R2DirectUploadService $r2DirectUploadService): string
    {
        return $r2DirectUploadService->finalDisk();
    }

    private function chatDirectVideoUploadPayload(array $uploadData): array
    {
        return [
            'direct_upload' => true,
            'provider' => $uploadData['provider'],
            'uid' => $uploadData['uid'],
            'upload_url' => $uploadData['upload_url'],
            'upload_method' => $uploadData['upload_method'],
            'upload_type' => $uploadData['upload_type'],
            'upload_headers' => $uploadData['upload_headers'],
            'upload_id' => $uploadData['upload_id'] ?? null,
            'part_size' => $uploadData['part_size'] ?? null,
            'parts' => $uploadData['parts'] ?? [],
            'upload_concurrency' => $uploadData['upload_concurrency'] ?? null,
            'expires_at' => $uploadData['expires_at'],
        ];
    }

    private function existingDirectVideoUploadResponse(Message $message)
    {
        $message = $this->loadMessageRealtimeRelations($message);

        return $this->responseSuccess([
            'data' => [
                'direct_upload' => false,
                'already_received' => true,
                'result' => [
                    'message' => MessageResource::make($message),
                    'media' => $message->media
                        ? \App\Http\Resources\User\Media\MediaResource::make($message->media)
                        : null,
                ],
            ],
        ]);
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
            logger()->warning('Chat message notification dispatch failed.', [
                'message_id' => $messageData->id,
                'chat_id' => $messageData->chat_id,
                'exception' => $th::class,
                'message' => $th->getMessage(),
            ]);
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
        if($request->hasFile('media')) $this->rejectServerMediaUpload();

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
            $clientUid = $request->input('client_uid');
            $chatData = Chat::participatedChats()->where('chat_id', $chatId)->first();

            if($chatData) {
                $payloadHash = filled($clientUid) ? $this->messagePayloadHash($request) : null;

                if(filled($clientUid)) {
                    $existingMessage = $this->findIdempotentMessage($chatData->id, me()->id, $clientUid);

                    if($existingMessage) {
                        if(! hash_equals((string) $existingMessage->client_payload_hash, (string) $payloadHash)) {
                            return $this->idempotencyConflictResponse();
                        }

                        return $this->responseSuccess([
                            'data' => MessageResource::make($this->loadMessageRealtimeRelations($existingMessage))
                        ]);
                    }
                }

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
                    'text_language' => $messageContentLanguage,
                    'client_uid' => filled($clientUid) ? $clientUid : null,
                    'client_payload_hash' => $payloadHash,
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

                try {
                    $messageData = $chatData->messages()->create($messageInsertData);
                } catch (QueryException $exception) {
                    $existingMessage = filled($clientUid)
                        ? $this->findIdempotentMessage($chatData->id, me()->id, $clientUid)
                        : null;

                    if(! $existingMessage) {
                        throw $exception;
                    }

                    if(! hash_equals((string) $existingMessage->client_payload_hash, (string) $payloadHash)) {
                        return $this->idempotencyConflictResponse();
                    }

                    return $this->responseSuccess([
                        'data' => MessageResource::make($this->loadMessageRealtimeRelations($existingMessage))
                    ]);
                }

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
                if(! empty($clientUid)) {
                    $messageData->setAttribute('client_uid', $clientUid);
                }

                try {
                    event(new MessageReceivedEvent($messageData, $clientUid));

                    $otherParticipants->each(function ($participantData) use ($messageData) {
                        $participantData->user->notify(new MessageReceivedNotification($messageData));
                    });
                } catch (Throwable $th) {
                    logger()->warning('Chat message notification dispatch failed.', [
                        'message_id' => $messageData->id,
                        'chat_id' => $messageData->chat_id,
                        'exception' => $th::class,
                        'message' => $th->getMessage(),
                    ]);
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

    public function forwardMessage(Request $request, int $messageId, ForwardMessageMediaService $mediaForwarder)
    {
        $validator = Validator::make($request->all(), [
            'destination_chat_id' => ['required', 'uuid'],
            'client_uid' => ['required', 'uuid'],
        ]);

        if(! $validator->passes()) {
            return $this->throwValidationError($validator);
        }

        $destinationChatId = $request->string('destination_chat_id')->toString();
        $destinationChat = Chat::participatedChats()->where('chat_id', $destinationChatId)->first();

        if(! $destinationChat) {
            return $this->responseResourceNotFoundError('Chat', $destinationChatId);
        }

        $clientUid = $request->string('client_uid')->toString();
        $payloadHash = hash('sha256', json_encode([
            'source_message_id' => $messageId,
            'destination_chat_id' => $destinationChatId,
        ], JSON_THROW_ON_ERROR));
        $existingMessage = $this->findIdempotentMessage($destinationChat->id, me()->id, $clientUid);

        if($existingMessage) {
            if(! hash_equals((string) $existingMessage->client_payload_hash, $payloadHash)) {
                return $this->idempotencyConflictResponse();
            }

            return $this->responseSuccess([
                'data' => MessageResource::make($this->loadMessageRealtimeRelations($existingMessage))
            ]);
        }

        $sourceMessage = Message::query()
            ->whereKey($messageId)
            ->where('is_deleted', false)
            ->whereHas('chat.participants', function ($query): void {
                $query->where('user_id', me()->id);
            })
            ->with(['media', 'linkSnapshot', 'user:id,first_name,last_name,username,avatar,verified'])
            ->first();

        if(! $sourceMessage) {
            return $this->responseResourceNotFoundError('Message', $messageId);
        }

        if($sourceMessage->type->isCall()) {
            return $this->responseError([
                'message' => 'This message cannot be forwarded.',
                'errors' => ['message' => 'Call messages cannot be forwarded.'],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if($sourceMessage->media && (! $sourceMessage->media->status->isProcessed() || blank($sourceMessage->media->source_path))) {
            return $this->responseError([
                'message' => 'This media is still processing.',
                'errors' => ['media' => 'Wait until media processing has finished before forwarding.'],
            ], Response::HTTP_CONFLICT);
        }

        $destinationParticipant = $destinationChat->participants()->where('user_id', me()->id)->first();
        $otherParticipants = $destinationChat->participants()->whereNot('user_id', me()->id)->with('user')->get();
        $sourceUser = $sourceMessage->user;
        $forwardedFrom = [
            'message_id' => (int) $sourceMessage->id,
            'user' => [
                'id' => $sourceUser?->id,
                'name' => $sourceUser?->name ?: 'Deleted account',
                'username' => $sourceUser?->username,
                'avatar_url' => $sourceUser?->avatar_url,
                'verified' => (bool) $sourceUser?->verified,
            ],
        ];

        try {
            $forwardedMessage = DB::transaction(function () use (
                $destinationChat,
                $destinationParticipant,
                $sourceMessage,
                $forwardedFrom,
                $clientUid,
                $payloadHash,
                $mediaForwarder
            ): Message {
                $message = $destinationChat->messages()->create([
                    'chat_uuid' => $destinationChat->chat_id,
                    'user_id' => me()->id,
                    'participant_id' => $destinationParticipant->id,
                    'content' => $sourceMessage->content,
                    'text_language' => $sourceMessage->text_language,
                    'type' => $sourceMessage->type,
                    'client_uid' => $clientUid,
                    'client_payload_hash' => $payloadHash,
                    'forwarded_from' => $forwardedFrom,
                ]);

                $mediaForwarder->attach($sourceMessage, $message);

                if($sourceMessage->linkSnapshot) {
                    $message->linkSnapshot()->create([
                        'url' => $sourceMessage->linkSnapshot->url,
                        'domain' => $sourceMessage->linkSnapshot->domain,
                        'title' => $sourceMessage->linkSnapshot->title,
                        'description' => $sourceMessage->linkSnapshot->description,
                        'metadata' => $sourceMessage->linkSnapshot->metadata,
                        'expires_at' => $sourceMessage->linkSnapshot->expires_at,
                    ]);
                }

                $destinationParticipant->update([
                    'last_read_message_id' => $message->id,
                    'last_read_at' => now(),
                ]);

                $this->touchChatAfterMessage($destinationChat);

                return $this->loadMessageRealtimeRelations($message);
            });
        } catch (QueryException $exception) {
            $existingMessage = $this->findIdempotentMessage($destinationChat->id, me()->id, $clientUid);

            if(! $existingMessage) {
                throw $exception;
            }

            if(! hash_equals((string) $existingMessage->client_payload_hash, $payloadHash)) {
                return $this->idempotencyConflictResponse();
            }

            return $this->responseSuccess([
                'data' => MessageResource::make($this->loadMessageRealtimeRelations($existingMessage))
            ]);
        }

        $this->notifyChatParticipants($forwardedMessage, $otherParticipants);

        return $this->responseSuccess([
            'data' => MessageResource::make($forwardedMessage)
        ]);
    }

    private function findIdempotentMessage(int $chatId, int $userId, string $clientUid): ?Message
    {
        return Message::query()
            ->where('chat_id', $chatId)
            ->where('user_id', $userId)
            ->where('client_uid', $clientUid)
            ->first();
    }

    private function messagePayloadHash(Request $request): string
    {
        $media = $request->file('media');
        $mediaHash = $media && $media->getRealPath() && is_file($media->getRealPath())
            ? hash_file('sha256', $media->getRealPath())
            : null;

        return hash('sha256', json_encode([
            'content' => (string) $request->input('content', ''),
            'parent_id' => $request->integer('parent_id') ?: null,
            'media_type' => $request->input('media_type'),
            'media_hash' => $mediaHash,
            'media_duration' => $request->input('media_duration'),
            'message_type' => $request->input('message_type'),
        ], JSON_THROW_ON_ERROR));
    }

    private function idempotencyConflictResponse()
    {
        return $this->responseError([
            'message' => 'This message send key has already been used for different content.',
            'errors' => ['client_uid' => 'A new client_uid is required for a different message.'],
        ], Response::HTTP_CONFLICT);
    }

    public function createDirectVideoUpload(Request $request, string $chatId, R2DirectUploadService $r2DirectUploadService)
    {
        $this->requireDirectVideoService($r2DirectUploadService);
        $request->validate([
            'parent_id' => ['nullable', 'integer'],
            'client_uid' => ['nullable', 'string', 'max:100'],
            'name' => ['nullable', 'string', 'max:255'],
            'size' => ['required', 'integer', 'min:1', 'max:' . $this->maxDirectVideoBytes()],
            'mime' => ['required', 'string', 'in:video/mp4,video/quicktime,video/webm,video/avi,video/x-msvideo,video/mpeg,video/x-m4v,video/3gpp,video/3gpp2,video/x-matroska'],
            'extension' => ['required', 'string', 'regex:/\A[a-zA-Z0-9]{2,8}\z/'],
            'width' => ['nullable', 'integer', 'min:1', 'max:20000'],
            'height' => ['nullable', 'integer', 'min:1', 'max:20000'],
            'duration_seconds' => ['required', 'integer', 'min:1', 'max:' . $this->maxDirectVideoDurationSeconds()],
        ]);

        if(! $r2DirectUploadService->isConfigured()) {
            return $this->responseSuccess([
                'data' => [
                    'direct_upload' => false,
                    'reason' => 'direct_upload_not_configured',
                ]
            ]);
        }

        $chatData = Chat::participatedChats()->where('chat_id', $chatId)->first();

        if(empty($chatData)) {
            return $this->responseResourceNotFoundError('Chat', $chatId);
        }

        $clientUid = $request->input('client_uid');
        $payloadHash = filled($clientUid) ? hash('sha256', json_encode([
            'parent_id' => $request->integer('parent_id') ?: null,
            'name' => (string) $request->input('name'),
            'size' => $request->integer('size', 0),
            'mime' => (string) $request->input('mime', 'video/mp4'),
            'extension' => (string) $request->input('extension', 'mp4'),
            'duration_seconds' => $request->integer('duration_seconds'),
            'width' => $request->integer('width') ?: null,
            'height' => $request->integer('height') ?: null,
        ], JSON_THROW_ON_ERROR)) : null;

        if(filled($clientUid)) {
            $existingMessage = $this->findIdempotentMessage($chatData->id, me()->id, $clientUid);

            if($existingMessage) {
                if(! hash_equals((string) $existingMessage->client_payload_hash, (string) $payloadHash)) {
                    return $this->idempotencyConflictResponse();
                }

                return $this->existingDirectVideoUploadResponse($existingMessage);
            }
        }

        $parentId = $request->integer('parent_id');

        if($parentId && ! $chatData->messages()->where('id', $parentId)->exists()) {
            return $this->responseResourceNotFoundError('Message', $parentId);
        }

        try {
            $participantData = $chatData->participants()->where('user_id', me()->id)->first();
            $uploadData = $r2DirectUploadService->createVideoUpload([
                'name' => (string) $request->input('name'),
                'size' => $request->integer('size', 0),
                'mime' => (string) $request->input('mime', 'video/mp4'),
                'extension' => (string) $request->input('extension', 'mp4'),
            ]);

            try {
                $messageData = $chatData->messages()->create([
                    'content' => null,
                    'user_id' => me()->id,
                    'chat_uuid' => $chatId,
                    'participant_id' => $participantData->id,
                    'parent_id' => $parentId ?: null,
                    'text_language' => '',
                    'type' => MessageType::VIDEO,
                    'client_uid' => filled($clientUid) ? $clientUid : null,
                    'client_payload_hash' => $payloadHash,
                ]);
            } catch (QueryException $exception) {
                $existingMessage = filled($clientUid)
                    ? $this->findIdempotentMessage($chatData->id, me()->id, $clientUid)
                    : null;

                if(! $existingMessage) {
                    throw $exception;
                }

                if(! hash_equals((string) $existingMessage->client_payload_hash, (string) $payloadHash)) {
                    return $this->idempotencyConflictResponse();
                }

                return $this->existingDirectVideoUploadResponse($existingMessage);
            }

            $participantData->update([
                'last_read_message_id' => $messageData->id,
                'last_read_at' => now()
            ]);

            $videoPublicDisk = $this->chatDirectVideoPublicDisk($r2DirectUploadService);
            $messageData->media()->create([
                'source_path' => $uploadData['path'],
                'type' => MediaType::VIDEO,
                'status' => MediaStatus::PROCESSING,
                'disk' => $uploadData['disk'],
                'extension' => $request->input('extension', 'mp4'),
                'mime' => $request->input('mime', 'video/mp4'),
                'size' => $request->integer('size', 0),
                'thumbnail_disk' => $videoPublicDisk,
                'metadata' => array_merge($this->chatDirectVideoPresentationMetadata($request), [
                    'duration' => parse_duration($request->integer('duration_seconds')),
                    'duration_seconds' => $request->integer('duration_seconds'),
                    'provider' => $uploadData['provider'],
                    'upload_state' => 'waiting_for_upload',
                    'upload_progress' => 0,
                    'upload_disk' => $uploadData['upload_disk'] ?? $uploadData['disk'],
                    'temp_disk' => $uploadData['disk'],
                    'temp_path' => $uploadData['path'],
                    'final_disk' => $uploadData['final_disk'],
                    'upload_url_expires_at' => $uploadData['expires_at'],
                    'upload_method' => $uploadData['upload_method'],
                    'upload_type' => $uploadData['upload_type'],
                    'upload_id' => $uploadData['upload_id'] ?? null,
                    'part_size' => $uploadData['part_size'] ?? null,
                    'parts_count' => count($uploadData['parts'] ?? []),
                    'processing_progress' => 0,
                    'processing_state' => 'waiting_for_upload',
                    'client_uid' => $request->input('client_uid'),
                    'original_name' => (string) $request->input('name'),
                    'original_size' => $request->integer('size', 0),
                ])
            ]);

            $chatData->update([
                'last_activity' => now()
            ]);

            if($chatData->type->isDirect()) {
                HiddenChat::where('chat_id', $chatData->id)->delete();
            }

            $messageData = $this->loadMessageRealtimeRelations($messageData->fresh());

            return $this->responseSuccess([
                'data' => array_merge($this->chatDirectVideoUploadPayload($uploadData), [
                    'media_id' => $messageData->media->id,
                    'message' => MessageResource::make($messageData),
                    'media' => $messageData->media ? \App\Http\Resources\User\Media\MediaResource::make($messageData->media) : null,
                ])
            ], Response::HTTP_CREATED);
        }
        catch (Exception $e) {
            return $this->responseValidationError([
                'message' => $e->getMessage(),
                'errors' => [
                    'video' => [
                        $e->getMessage()
                    ]
                ]
            ]);
        }
    }

    public function updateDirectVideoUploadProgress(Request $request, string $chatId)
    {
        $request->validate([
            'media_id' => ['required', 'integer'],
            'uid' => ['required', 'string', 'max:255'],
            'upload_progress' => ['required', 'integer', 'min:0', 'max:100'],
            'upload_state' => ['nullable', 'string', 'in:waiting_for_upload,uploading,failed'],
        ]);

        $media = $this->findOwnedChatDirectVideoMedia($chatId, $request->integer('media_id'), (string) $request->input('uid'));

        if(empty($media)) {
            return $this->responseNotFoundError();
        }

        $metadata = $media->metadata ?? [];

        if(data_get($metadata, 'upload_state') === 'uploaded') {
            return $this->responseSuccess([
                'data' => [
                    'media' => \App\Http\Resources\User\Media\MediaResource::make($media)
                ]
            ]);
        }

        $progress = $request->integer('upload_progress');

        if($request->input('upload_state') === 'failed') {
            $metadata['upload_state'] = 'failed';
            $metadata['upload_failed_at'] = now()->toIso8601String();
            $metadata['processing_state'] = 'failed';
            $media->status = MediaStatus::FAILED;
        }
        else {
            $metadata['upload_state'] = $progress > 0 ? 'uploading' : data_get($metadata, 'upload_state', 'waiting_for_upload');
            $media->status = MediaStatus::PROCESSING;
        }

        $metadata['upload_progress'] = $progress;
        $metadata['upload_progress_updated_at'] = now()->toIso8601String();
        $media->metadata = $metadata;
        $media->save();

        return $this->responseSuccess([
            'data' => [
                'media' => \App\Http\Resources\User\Media\MediaResource::make($media->refresh())
            ]
        ]);
    }

    public function completeDirectVideoUpload(Request $request, string $chatId, R2DirectUploadService $r2DirectUploadService)
    {
        return $this->serializeMediaCompletion($request, fn () => $this->finishVideoUpload($request, $chatId, $r2DirectUploadService));
    }

    private function finishVideoUpload(Request $request, string $chatId, R2DirectUploadService $r2DirectUploadService)
    {
        $request->validate([
            'media_id' => ['required', 'integer'],
            'uid' => ['required', 'string', 'max:255'],
            'upload_id' => ['nullable', 'string', 'max:2048'],
            'parts' => ['nullable', 'array'],
            'parts.*.part_number' => ['required_with:parts', 'integer', 'min:1', 'max:10000'],
            'parts.*.etag' => ['nullable', 'string', 'max:255'],
        ]);

        $media = $this->findOwnedChatDirectVideoMedia($chatId, $request->integer('media_id'), (string) $request->input('uid'));

        if(empty($media)) {
            return $this->responseNotFoundError();
        }

        $metadata = $media->metadata ?? [];

        if(data_get($metadata, 'upload_state') === 'uploaded' || $media->status->isProcessed()) {
            return $this->responseSuccess(['data' => [
                'message' => MessageResource::make($this->loadMessageRealtimeRelations($media->mediaable->fresh())),
                'media' => \App\Http\Resources\User\Media\MediaResource::make($media),
            ]]);
        }

        try {
            if(data_get($metadata, 'upload_type') === 'multipart' && data_get($metadata, 'upload_state') !== 'uploaded') {
                $r2DirectUploadService->completeMultipartUpload(
                    $media->source_path,
                    (string) ($request->input('upload_id') ?: data_get($metadata, 'upload_id')),
                    $request->array('parts'),
                    $media->disk,
                    (int) data_get($metadata, 'parts_count', 0)
                );
            }

            if(! $r2DirectUploadService->uploaded($media->source_path, $media->disk)) {
                throw new Exception('Direct chat video file was not found on R2.');
            }

            $r2DirectUploadService->verifyUploadedVideo($media->source_path, $media->disk, (int) $media->size);
        }
        catch (Exception $e) {
            return $this->responseValidationError([
                'message' => $e->getMessage(),
                'errors' => [
                    'video' => [
                        $e->getMessage()
                    ]
                ]
            ]);
        }

        $temporaryPath = $media->source_path;
        $temporaryDisk = $media->disk;

        try {
            $published = $r2DirectUploadService->publishUploadedObject(
                $temporaryPath,
                'uploads/chats/videos',
                $media->extension ?: 'mp4',
                $media->mime ?: 'video/mp4',
                $temporaryDisk
            );
        }
        catch (Exception $e) {
            return $this->responseValidationError([
                'message' => $e->getMessage(),
                'errors' => ['video' => [$e->getMessage()]],
            ]);
        }

        $completion = DB::transaction(function () use ($media, $published, $temporaryPath, $temporaryDisk): ?array {
            $lockedMedia = $media->newQuery()
                ->whereKey($media->getKey())
                ->lockForUpdate()
                ->first();

            if(! $lockedMedia) {
                return null;
            }

            $metadata = $lockedMedia->metadata ?? [];

            if(data_get($metadata, 'upload_state') === 'uploaded' || $lockedMedia->status->isProcessed()) {
                return [
                    'should_dispatch' => false,
                    'media' => $lockedMedia,
                    'message' => $this->loadMessageRealtimeRelations($lockedMedia->mediaable->fresh()),
                ];
            }

            $metadata = array_merge($metadata, [
                'provider' => 'r2',
                'upload_state' => 'uploaded',
                'upload_progress' => 100,
                'upload_completed_at' => now()->toIso8601String(),
                'processing_progress' => 100,
                'processing_state' => 'processed',
                'processing_mode' => 'original_direct',
                'background_processing_state' => 'skipped',
                'background_processing_progress' => 100,
                'temp_path' => data_get($metadata, 'temp_path', $temporaryPath),
                'temp_disk' => data_get($metadata, 'temp_disk', $temporaryDisk),
                'final_disk' => $published['disk'],
                'processing_updated_at' => now()->toIso8601String(),
                'original_size' => (int) ($published['size'] ?: data_get($metadata, 'original_size', 0)),
            ]);

            $lockedMedia->source_path = $published['path'];
            $lockedMedia->disk = $published['disk'];
            $lockedMedia->size = $published['size'];
            $lockedMedia->metadata = $metadata;
            $lockedMedia->status = MediaStatus::PROCESSED;
            $lockedMedia->save();

            return [
                'should_dispatch' => true,
                'media' => $lockedMedia->refresh(),
                'message' => $this->loadMessageRealtimeRelations($lockedMedia->mediaable->fresh()),
            ];
        });

        if(! $completion) {
            return $this->responseNotFoundError();
        }

        $media = $completion['media'];
        $messageData = $completion['message'];
        $metadata = $media->metadata ?? [];
        $clientUid = (string) data_get($metadata, 'client_uid', $request->input('client_uid'));

        if(! $completion['should_dispatch']) {
            return $this->responseSuccess([
                'data' => [
                    'message' => MessageResource::make($messageData),
                    'media' => \App\Http\Resources\User\Media\MediaResource::make($media),
                ],
            ]);
        }

        try {
            event(new MessageReceivedEvent($messageData, $clientUid ?: null));

            $messageData->chat->participants()
                ->whereNot('user_id', me()->id)
                ->with('user')
                ->get()
                ->each(function ($participantData) use ($messageData) {
                    $participantData->user->notify(new MessageReceivedNotification($messageData));
                });
        }
        catch (Throwable $th) {
            logger()->warning('Chat media notification dispatch failed.', [
                'message_id' => $messageData->id,
                'chat_id' => $messageData->chat_id,
                'exception' => $th::class,
                'message' => $th->getMessage(),
            ]);
        }

        return $this->responseSuccess([
            'data' => [
                'message' => MessageResource::make($messageData),
                'media' => \App\Http\Resources\User\Media\MediaResource::make($media->refresh()),
            ]
        ]);
    }

    public function initAudioMessage(Request $request)
    {
        $this->rejectServerMediaUpload('audio');

        $validator = Validator::make([
            'chat_id' => $request->input('chat_id'),
            'parent_id' => $request->input('parent_id'),
            'client_uid' => $request->input('client_uid'),
            'duration_seconds' => $request->input('duration_seconds'),
            'extension' => $request->input('extension'),
            'mime_type' => $request->input('mime_type'),
            'file_name' => $request->input('file_name'),
        ], [
            'chat_id' => ['required', 'uuid'],
            'parent_id' => ['nullable', 'integer'],
            'client_uid' => ['nullable', 'string', 'max:100'],
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

        $clientUid = $request->input('client_uid');
        $payloadHash = filled($clientUid) ? hash('sha256', json_encode([
            'parent_id' => $request->integer('parent_id') ?: null,
            'duration_seconds' => $request->integer('duration_seconds'),
            'extension' => $request->input('extension'),
            'mime_type' => $request->input('mime_type'),
            'file_name' => $request->input('file_name'),
        ], JSON_THROW_ON_ERROR)) : null;

        if(filled($clientUid)) {
            $existingMessage = $this->findIdempotentMessage($chatData->id, me()->id, $clientUid);

            if($existingMessage) {
                if(! hash_equals((string) $existingMessage->client_payload_hash, (string) $payloadHash)) {
                    return $this->idempotencyConflictResponse();
                }

                return $this->responseSuccess([
                    'data' => MessageResource::make($this->loadMessageRealtimeRelations($existingMessage))
                ]);
            }
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
            'client_uid' => filled($clientUid) ? $clientUid : null,
            'client_payload_hash' => $payloadHash,
        ];

        if($parentId) {
            $replyableMessageExists = $chatData->messages()->where('id', $parentId)->exists();

            if(empty($replyableMessageExists)) {
                return $this->responseResourceNotFoundError('Message', $parentId);
            }

            $messageInsertData['parent_id'] = $parentId;
        }

        try {
            $messageData = $chatData->messages()->create($messageInsertData);
        } catch (QueryException $exception) {
            $existingMessage = filled($clientUid)
                ? $this->findIdempotentMessage($chatData->id, me()->id, $clientUid)
                : null;

            if(! $existingMessage) {
                throw $exception;
            }

            if(! hash_equals((string) $existingMessage->client_payload_hash, (string) $payloadHash)) {
                return $this->idempotencyConflictResponse();
            }

            return $this->responseSuccess([
                'data' => MessageResource::make($this->loadMessageRealtimeRelations($existingMessage))
            ]);
        }

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
        $this->rejectServerMediaUpload('audio');
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
