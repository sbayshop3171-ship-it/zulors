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
use App\Rules\X\XRule;
use App\Models\Comment;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\Reaction\ReactionService;
use App\Services\Timeline\UserInterestService;
use App\Actions\Comment\DeleteCommentAction;
use App\Traits\Http\Api\SupportsApiResponses;
use Illuminate\Auth\Access\AuthorizationException;
use App\Http\Resources\User\Timeline\CommentResource;
use App\Http\Resources\User\Timeline\ReactionCollection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Notifications\User\Post\PostCommentedNotification;
use App\Notifications\User\Post\CommentReactedNotification;
use App\Notifications\User\Mention\CommentMentionNotification;

class CommentController extends Controller
{
    use SupportsApiResponses, AuthorizesRequests;

    public function createComment(Request $request, UserInterestService $userInterestService)
    {
        $request->validate([
            'post_id' => ['required', 'integer'],
            'parent_id' => ['nullable', 'integer'],
            'content' => ['required', 'string', 'min:1', XRule::join('max', config('post.comments.validation.max'))]
        ]);

        $postId = $request->get('post_id');
        $parentId = $request->get('parent_id');

        $commentContent = $request->get('content');

        $postData = Post::activeById($postId)->first();

        if(empty($postData)) {
            return $this->responseResourceNotFoundError('Post', $postId);
        }
        
        if ($parentId) {
            $commentParentData = $postData->comments()->find($parentId);

            if(empty($commentParentData)) {
                return $this->responseResourceNotFoundError('Comment', $parentId);
            }
        }

        $comment = $postData->comments()->create([
            'content' => $commentContent,
            'user_id' => me()->id,
            'parent_id' => (empty($parentId)) ? null : $parentId,
            'text_language' => $postData->text_language
        ]);

        $comment->text_language = $postData->getContentLanguage();
        $comment->save();

        $postData->comments_count = $postData->comments()->count();
        $postData->save();

        $userInterestService->recordPostInteraction(me(), $postData, UserInterestService::EVENT_COMMENT);

        if(! $postData->is_owner && empty($parentId)) {
            $postData->user->notify(new PostCommentedNotification($postData, $commentContent));
        }

        else {
            if(! empty($commentParentData) && ! $commentParentData->is_owner) {
                $commentParentData->user->notify(new CommentMentionNotification($commentParentData, $commentContent));
            }
        }

        return $this->responseSuccess([
            'data' => [
                'comment' => CommentResource::make($comment),
                'post' => [
                    'comments_count' => [
                        'raw' => $postData->comments_count,
                        'formatted' => Num::abbreviate($postData->comments_count)
                    ]
                ]
            ]
        ]);
    }

    public function deleteComment(Request $request)
    {
        $commentData = Comment::where('id', $request->integer('id'))->first();

        if($commentData) {
            try {
                $this->authorize('delete', $commentData);

                $postData = $commentData->post;
        
                (new DeleteCommentAction($commentData))->execute();

                $postData->comments_count = $postData->comments()->count();
                $postData->save();
        
                return $this->responseSuccess([
                    'data' => [
                        'post' => [
                            'comments_count' => [
                                'raw' => Num::abbreviate($postData->comments_count),
                                'formatted' => Num::abbreviate($postData->comments_count)
                            ]
                        ]
                    ]
                ]);
            } 
            
            catch (AuthorizationException $e) {
                return $this->responseUnauthorizedError();
            }
        }

        return $this->responseError([
            'message' => 'Comment with such ID does not exist.',
            'errors' => [
                'id' => [
                    'Comment with such ID does not exist.'
                ]
            ]
        ]);
    }

    public function addReaction(Request $request, ReactionService $reactionService)
    {
        $request->validate([
            'comment_id' => ['required', 'integer'],
            'unified_id' => ['required', 'string', 'min:4', 'max:32']
        ]);

        $reactionUnifiedId = $request->get('unified_id');
        $commentId = $request->get('comment_id');

        try {
            $commentData = Comment::find($commentId);

            if ($commentData) {
                $isReactionAdded = $reactionService
                    ->setUserId(me()->id)
                    ->setReactable($commentData)
                    ->setUnifiable(strtolower($reactionUnifiedId))
                    ->handleReaction();

                if(! $commentData->is_owner && $isReactionAdded) {
                    $commentData->user->notify(new CommentReactedNotification($commentData, strtolower($reactionUnifiedId)));
                }

                return $this->responseSuccess([
                    'data' => ReactionCollection::make($commentData->reactions)
                ]);
            }

            return $this->responseResourceNotFoundError('Comment', $commentId);
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
}
