<?php
/*
|--------------------------------------------------------------------------
| Zulors - The Ultimate Zulors Web Application.
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

use Illuminate\Support\Facades\Route;

Route::get('/chats', [App\Http\Controllers\Api\User\Chat\ChatController::class, 'getChats']);
Route::get('/archive', [App\Http\Controllers\Api\User\Chat\ChatController::class, 'getArchive']);
Route::get('/unread/count', [App\Http\Controllers\Api\User\Chat\ChatController::class, 'getUnreadCount']);
Route::post('/chats/create', [App\Http\Controllers\Api\User\Chat\ChatController::class, 'createChat']);
Route::get('/search/bootstrap', [App\Http\Controllers\Api\User\Chat\ChatController::class, 'getSearchBootstrap']);
Route::get('/search', [App\Http\Controllers\Api\User\Chat\ChatController::class, 'search']);
Route::post('/search/recent', [App\Http\Controllers\Api\User\Chat\ChatController::class, 'storeSearchRecent']);
Route::delete('/search/recent/{userId}', [App\Http\Controllers\Api\User\Chat\ChatController::class, 'deleteSearchRecent']);
Route::delete('/search/recent', [App\Http\Controllers\Api\User\Chat\ChatController::class, 'clearSearchRecents']);

Route::post('/calls/start', [App\Http\Controllers\Api\User\Chat\CallController::class, 'start'])->middleware('throttle:120,1');
Route::get('/calls/ice-servers', [App\Http\Controllers\Api\User\Chat\CallController::class, 'iceServers'])->middleware('throttle:30,1');
Route::get('/calls/{callUuid}', [App\Http\Controllers\Api\User\Chat\CallController::class, 'show']);
Route::get('/calls/{callUuid}/media-token', [App\Http\Controllers\Api\User\Chat\CallController::class, 'mediaToken'])->middleware('throttle:120,1');
Route::post('/calls/{callUuid}/answer', [App\Http\Controllers\Api\User\Chat\CallController::class, 'answer']);
Route::post('/calls/{callUuid}/decline', [App\Http\Controllers\Api\User\Chat\CallController::class, 'decline']);
Route::post('/calls/{callUuid}/end', [App\Http\Controllers\Api\User\Chat\CallController::class, 'end']);
Route::post('/calls/{callUuid}/signal', [App\Http\Controllers\Api\User\Chat\CallController::class, 'signal'])->middleware('throttle:360,1');
Route::post('/calls/{callUuid}/heartbeat', [App\Http\Controllers\Api\User\Chat\CallController::class, 'heartbeat'])->middleware('throttle:120,1');
Route::post('/calls/{callUuid}/quality', [App\Http\Controllers\Api\User\Chat\CallController::class, 'quality'])->middleware('throttle:120,1');

Route::get('/groups/create', [App\Http\Controllers\Api\User\Chat\GroupController::class, 'createDraftGroup']);
Route::get('/groups/{chatId}/edit', [App\Http\Controllers\Api\User\Chat\GroupController::class, 'editGroup']);
Route::get('/groups/{chatId}/show', [App\Http\Controllers\Api\User\Chat\GroupController::class, 'showGroup']);
Route::get('/groups/{chatId}/participants', [App\Http\Controllers\Api\User\Chat\GroupController::class, 'getGroupParticipants']);
Route::get('/groups/{chatId}/recent-joins', [App\Http\Controllers\Api\User\Chat\GroupController::class, 'getGroupRecentJoins']);
Route::post('/groups/avatar/update', [App\Http\Controllers\Api\User\Chat\GroupController::class, 'updateGroupAvatar']);
Route::post('/groups/update', [App\Http\Controllers\Api\User\Chat\GroupController::class, 'updateGroup']);
Route::delete('/groups/participant/delete', [App\Http\Controllers\Api\User\Chat\GroupController::class, 'deleteParticipants']);
Route::delete('/groups/delete', [App\Http\Controllers\Api\User\Chat\GroupController::class, 'deleteGroup']);
Route::post('/groups/invite/send', [App\Http\Controllers\Api\User\Chat\GroupController::class, 'inviteParticipants']);
Route::post('/groups/invite/search', [App\Http\Controllers\Api\User\Chat\GroupController::class, 'searchInvitees']);
Route::post('/groups/invite/accept', [App\Http\Controllers\Api\User\Chat\GroupController::class, 'acceptInvite']);
Route::post('/groups/invite/decline', [App\Http\Controllers\Api\User\Chat\GroupController::class, 'declineInvitation']);
Route::post('/groups/leave', [App\Http\Controllers\Api\User\Chat\GroupController::class, 'leaveGroup']);
Route::get('/groups/{chatId}/invite/pending', [App\Http\Controllers\Api\User\Chat\GroupController::class, 'getPendingInvitations']);


Route::get('/chats/requests', [App\Http\Controllers\Api\User\Chat\ChatController::class, 'getChatRequests']);
Route::get('/chats/requests/count', [App\Http\Controllers\Api\User\Chat\ChatController::class, 'getChatRequestsCount']);
Route::post('/chats/launch', [App\Http\Controllers\Api\User\Chat\ChatController::class, 'launchChat']);
Route::post('/chats/launcher-send', [App\Http\Controllers\Api\User\Chat\ChatController::class, 'launcherSendMessage']);
Route::post('/send', [App\Http\Controllers\Api\User\Chat\ChatController::class, 'sendMessage']);
Route::get('/chat/{chatId}', [App\Http\Controllers\Api\User\Chat\ChatController::class, 'getChatData']);
Route::post('/chat/message/add-reaction', [App\Http\Controllers\Api\User\Chat\ChatController::class, 'addReaction']);
Route::delete('/chat/message/delete', [App\Http\Controllers\Api\User\Chat\ChatController::class, 'deleteMessage']);
Route::get('/chat/{chatId}/messages', [App\Http\Controllers\Api\User\Chat\ChatController::class, 'getChatMessages']);
Route::get('/chat/{chatId}/participants', [App\Http\Controllers\Api\User\Chat\ChatController::class, 'getChatParticipants']);
Route::delete('/chat/{chatId}/clear', [App\Http\Controllers\Api\User\Chat\ChatController::class, 'clearConversation']);
Route::delete('/chat/{chatId}/delete', [App\Http\Controllers\Api\User\Chat\ChatController::class, 'deleteChat']);
Route::delete('/chat/{chatId}/archive', [App\Http\Controllers\Api\User\Chat\ChatController::class, 'archiveChat']);
Route::delete('/chat/{chatId}/unarchive', [App\Http\Controllers\Api\User\Chat\ChatController::class, 'unarchiveChat']);
Route::get('/chat/{chatId}/read', [App\Http\Controllers\Api\User\Chat\ChatController::class, 'markAsRead']);
