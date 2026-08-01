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

namespace App\Http\Controllers\Api\User\Timeline;

use Exception;
use App\Models\Post;
use App\Support\Num;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Enums\Media\MediaType;
use App\Enums\Post\PostStatus;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;
use App\Actions\Post\DeletePostAction;
use App\Services\Text\LinkPreviewService;
use App\Services\Reaction\ReactionService;
use App\Services\Timeline\UserInterestService;
use App\Services\Timeline\TopicExtractionService;
use App\Services\Safety\SafetyService;
use App\Traits\Http\Api\SupportsApiResponses;
use Illuminate\Http\Response;
use App\Events\User\Timeline\PostCreatedEvent;
use App\Events\User\Timeline\PostUpdatedEvent;
use App\Events\User\Timeline\PublicTimelinePostCreatedEvent;
use App\Http\Resources\User\Timeline\QuoteResource;
use App\Http\Resources\User\Timeline\TimelineResource;
use App\Http\Resources\User\Morph\LinkSnapshotResource;
use App\Http\Resources\User\Timeline\ReactionCollection;
use App\Notifications\User\Post\PostReactedNotification;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Http\Resources\User\Timeline\Editor\DraftPostResource;
use App\Traits\Http\Controllers\Api\User\Timeline\ValidatesPollData;
use App\Traits\Http\Controllers\Api\User\Timeline\ValidatesPostData;
use App\Traits\Http\Controllers\Api\User\Timeline\InteractsWithDraftPost;

class PostController extends Controller
{
    use SupportsApiResponses,
        AuthorizesRequests,
        InteractsWithDraftPost,
        ValidatesPollData,
        ValidatesPostData;
    
    public function createPost(Request $request, SafetyService $safetyService)
    {
        if($safetyService->isFrozen(me())) {
            return $this->responseError([
                'message' => 'Posting temporarily frozen due to spam-like activity.',
                'errors' => [
                    'safety' => [
                        'Posting temporarily frozen due to spam-like activity.'
                    ]
                ],
                'data' => [
                    'retry_after_seconds' => $safetyService->freezeRemainingSeconds(me())
                ]
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $this->initializePostAndValidateData($request);

        if($videoAttachmentError = $this->validateDraftVideoAttachmentExists()) {
            return $videoAttachmentError;
        }

        $this->defineAndSetPostStatus();

        if($this->draftPost->content) {
            $this->draftPost->text_language = $this->draftPost->getContentLanguage();
        }

        $quotedPostId = $request->integer('quoted_post_id', null);

        if($quotedPostId) {
            $quotedPost = Post::activeById($quotedPostId)->first();

            if($quotedPost) {
                $this->draftPost->quote_post_id = $quotedPost->id;
                $this->draftPost->is_quoting = true;

                $quotedPost->increment('quotes_count', 1);
            }
        }

        $postMarks = $request->array('marks', []);

        if(! empty($postMarks['is_ai_generated'])) {
            $this->draftPost->is_ai_generated = true;
        }

        if(! empty($postMarks['is_sensitive'])) {
            $this->draftPost->is_sensitive = true;
        }
        
        $this->draftPost->save();

        $finalPost = $this->getFinialPost(); 

        app(TopicExtractionService::class)->syncPostTopics($finalPost);

        $finalPost->user->increment('publications_count', 1);

        event(new PostCreatedEvent($finalPost));

        if($finalPost->status === PostStatus::ACTIVE) {
            broadcast(new PublicTimelinePostCreatedEvent($finalPost))->toOthers();
        }

        $safetyService->recordPostCreated($finalPost->user);

        return $this->responseSuccess([
            'data' => TimelineResource::make($finalPost)
        ]);
    }

    public function bookmarkPost(Request $request)
    {
        $postId = $request->integer('id');

        $postData = Post::activeById($postId)->first();

        if($postData) {
            $bookmarkedStatus = $postData->isBookmarkedBy(me()->id);

            if($bookmarkedStatus) {
                $postData->removeBookmark(me()->id);
                app(UserInterestService::class)->recordPostInteraction(me(), $postData, UserInterestService::EVENT_BOOKMARK, -4.0);
            }
            else {
                $postData->addBookmark(me()->id);
                app(UserInterestService::class)->recordPostInteraction(me(), $postData, UserInterestService::EVENT_BOOKMARK);
            }

            return $this->responseSuccess([
                'data' => [
                    'bookmarked' => (! $bookmarkedStatus)
                ]
            ]);
        }
        else {
            return $this->responseResourceNotFoundError('Post', $postId);
        }
    }

    public function sharePost(Request $request)
    {
        $postId = $request->integer('id');

        $postData = Post::activeById($postId)->first();

        if($postData) {
            $postData->increment('shares_count');
            $postData->refresh();

            app(UserInterestService::class)->recordPostInteraction(me(), $postData, UserInterestService::EVENT_SHARE);

            return $this->responseSuccess([
                'data' => [
                    'shares_count' => [
                        'raw' => $postData->shares_count,
                        'formatted' => Num::abbreviate($postData->shares_count)
                    ]
                ]
            ]);
        }

        return $this->responseResourceNotFoundError('Post', $postId);
    }

    public function updatePost(Request $request, TopicExtractionService $topicExtractionService)
    {
        $postId = $request->integer('id');

        $postData = Post::query()
            ->where('id', $postId)
            ->whereIn('status', [
                PostStatus::ACTIVE,
                PostStatus::PROCESSING_VIDEO,
            ])
            ->firstOrFail();

        $this->authorize('update', $postData);

        $content = $this->sanitizePostContent($request->get('content', ''));

        $this->validatePostUpdateData($postData, [
            'content' => $content
        ]);

        $postData->content = $content;
        $postData->text_language = empty($content) ? '' : $postData->getContentLanguage();
        $postData->edited = true;
        $postData->save();

        $topicExtractionService->syncPostTopics($postData);

        $updatedPost = Post::timelineFormatPosts(true)->findOrFail($postData->id);

        try {
            broadcast(new PostUpdatedEvent($updatedPost))->toOthers();
        }
        catch(\Throwable $exception) {
            report($exception);
        }

        return $this->responseSuccess([
            'data' => TimelineResource::make($updatedPost)
        ]);
    }

    public function getDraftPost(Request $request)
    {
        $this->fetchOrInitializeDraftPost();

        $quotedPostId = $request->integer('quoted_post_id', null);

        $responseData = [
            'data' => [
                'draft' => null
            ]
        ];

        if ($this->draftPost->exists) {
            $responseData['data']['draft'] = DraftPostResource::make($this->draftPost);
        }

        if($quotedPostId) {
            $quotedPost = Post::activeById($quotedPostId)->with('user')->first();

            if($quotedPost) {
                $responseData['data']['quoted_post'] = QuoteResource::make($quotedPost);
            }
        }

        return $this->responseSuccess($responseData);
    }

    private function defineAndSetPostStatus()
    {
        $this->draftPost->status = PostStatus::ACTIVE;

        if($this->draftPost->type->isVideo() && ! $this->canPlayVideoImmediately()) {
            $this->draftPost->status = PostStatus::PROCESSING_VIDEO;
        }
    }

    private function canPlayVideoImmediately(): bool
    {
        $videoMedia = $this->draftPost->media()
            ->where('type', MediaType::VIDEO->value)
            ->latest('id')
            ->first();

        if(! $videoMedia || blank($videoMedia->source_path)) {
            return false;
        }

        if($videoMedia->status->isProcessed()) {
            return true;
        }

        return in_array(data_get($videoMedia->metadata, 'provider'), ['r2_temp', 'r2_direct'], true)
            && data_get($videoMedia->metadata, 'upload_state') === 'uploaded';
    }

    private function validateDraftVideoAttachmentExists()
    {
        if(! $this->draftPost->type->isVideo()) {
            return null;
        }

        $videoMedia = $this->draftPost->media()
            ->where('type', MediaType::VIDEO->value)
            ->latest('id')
            ->first();

        if($videoMedia) {
            $uploadProvider = data_get($videoMedia->metadata, 'provider');
            $uploadState = data_get($videoMedia->metadata, 'upload_state');

            if(
                in_array($uploadProvider, ['r2_temp', 'r2_direct'], true) &&
                $uploadState === 'failed'
            ) {
                return $this->responseValidationError([
                    'message' => 'Video upload failed. Please remove it and try again.',
                    'errors' => [
                        'video' => [
                            'Video upload failed. Please remove it and try again.'
                        ]
                    ]
                ]);
            }

            if(
                in_array($uploadProvider, ['r2_temp', 'r2_direct'], true) &&
                $uploadState !== 'uploaded' &&
                ! in_array($uploadState, ['waiting_for_upload', 'uploading'], true)
            ) {
                return $this->responseValidationError([
                    'message' => 'Please wait until the video upload reaches 100%.',
                    'errors' => [
                        'video' => [
                            'Please wait until the video upload reaches 100%.'
                        ]
                    ]
                ]);
            }

            return null;
        }

        return $this->responseValidationError([
            'message' => 'Please attach a video before publishing.',
            'errors' => [
                'video' => [
                    'Please attach a video before publishing.'
                ]
            ]
        ]);
    }

    private function initializePostAndValidateData(Request $request)
    {
        $this->fetchOrInitializeDraftPost();

        $this->validatePostData([
            'content' => $request->get('content', null)
        ]);

        if($request->filled('content')) {
            $this->draftPost->content = normalize_nls($request->get('content', ''));
        }

        if($this->draftPost->type->isPoll()) {
            $this->validatePollData([
                'poll_options' => $request->get('poll_options', [])
            ]);

            if(! $this->draftPost->exists) {
                $this->draftPost->save();
            }

            $this->draftPost->poll->update([
                'choices' => $request->get('poll_options')
            ]);
        }
    }

    private function validatePostUpdateData(Post $postData, array $data): void
    {
        $requiresContent = ($postData->type->isTextified() || $postData->type->isPoll());
        $contentRules = [
            $requiresContent ? 'required' : 'nullable',
            'string',
            'max:' . config('post.validation.content.max')
        ];

        if($requiresContent || $data['content'] !== '') {
            $contentRules[] = 'min:' . config('post.validation.content.min');
        }

        $validator = Validator::make($data, [
            'content' => $contentRules
        ]);

        if ($validator->fails()) {
            $this->throwValidationError($validator);
        }
    }

    private function sanitizePostContent(?string $content): string
    {
        return trim(normalize_nls(strip_tags((string) $content)));
    }

    public function deletePost(Request $request)
    {
        $postId = $request->integer('id');

        $postData = Post::find($postId);

        if(! $postData) {
            return $this->responseSuccess([
                'data' => null
            ]);
        }

        $this->authorize('delete', $postData);

        (new DeletePostAction($postData))->execute();

        $postData->user->decrementValue('publications_count', 1);

        return $this->responseSuccess([
            'data' => null
        ]);
    }

    private function getFinialPost()
    {
        return $this->draftPost->refresh();
    }

    public function addReaction(Request $request, ReactionService $reactionService, UserInterestService $userInterestService)
    {
        $request->validate([
            'post_id' => ['required', 'integer'],
            'unified_id' => ['required', 'string', 'min:4', 'max:32']
        ]);

        $reactionUnifiedId = $request->get('unified_id');
        $postId = $request->get('post_id');

        try {
            $postData = Post::activeById($postId)->firstOrFail();

            $isReactionAdded = $reactionService
                ->setUserId(me()->id)
                ->setReactable($postData)
                ->setUnifiable(strtolower($reactionUnifiedId))
                ->handleReaction();
                
            if (! $postData->is_owner && $isReactionAdded) {
                $postData->user->notify(new PostReactedNotification($postData, strtolower($reactionUnifiedId)));
            }

            if($isReactionAdded) {
                $userInterestService->recordPostInteraction(me(), $postData, UserInterestService::EVENT_REACTION);
            }

            return $this->responseSuccess([
                'data' => ReactionCollection::make($postData->reactions)
            ]);
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

    public function previewLink(Request $request)
    {
        $request->validate([
            'url' => ['required', 'string', 'url']
        ]);

        $this->fetchOrInitializeDraftPost();

        $this->draftPost->linkSnapshot()->delete();

        $url = $request->get('url');

        $linkPreviewService = app(LinkPreviewService::class);

        $linkPreview = $linkPreviewService->previewLink($url);

        // Save the draft post first to ensure it has an ID
        $this->draftPost->content = $url;
        $this->draftPost->save();

        $linkSnapshotData = $this->draftPost->linkSnapshot()->create([
            'title' => Str::limit($linkPreview['title'], 250),
            'description' => Str::limit($linkPreview['description'], 250),
            'url' => Str::limit($linkPreview['url'], 250),
            'metadata' => [
                'is_fallback' => isset($linkPreview['is_fallback']) ? $linkPreview['is_fallback'] : false,
                'preview_image_base64' => $linkPreview['preview_image_base64']
            ]
        ]);
        
        return $this->responseSuccess([
            'data' => LinkSnapshotResource::make($linkSnapshotData)
        ]);
    }

    public function deleteLinkSnapshot()
    {
        $this->fetchOrInitializeDraftPost();

        $this->draftPost->linkSnapshot()->delete();

        $this->draftPost->content = '';
        $this->draftPost->save();

        return $this->responseSuccess([
            'data' => null
        ]);
    }
}
