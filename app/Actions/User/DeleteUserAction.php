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

namespace App\Actions\User;

use App\Actions\Ad\DeleteAdAction;
use App\Actions\Chat\DeleteChatAction;
use App\Actions\Chat\DeleteGroupAction;
use App\Actions\Chat\DeleteMessageAction;
use App\Actions\Comment\DeleteCommentAction;
use App\Actions\JobListing\DeleteJobAction;
use App\Actions\Post\DeletePostAction;
use App\Actions\Product\DeleteProductAction;
use App\Actions\Story\DeleteStoryFrameAction;
use App\Enums\Chat\ChatType;
use App\Enums\User\FollowStatus;
use App\Models\AuthorshipRequest;
use App\Models\Block;
use App\Models\Bookmark;
use App\Models\Chat;
use App\Models\ChatInvite;
use App\Models\Comment;
use App\Models\HiddenChat;
use App\Models\HiddenMessage;
use App\Models\Mute;
use App\Models\Post;
use App\Models\Report;
use App\Models\StoryView;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Throwable;

class DeleteUserAction
{
    private User $userData;

    private $commentPosts = [];

    public function __construct(User $userData)
    {
        $this->userData = $userData;
    }

    public function execute()
    {
        try {
            $this->unfollowAllUsers();

            $this->deleteAllBlocks();

            $this->deleteAllMutes();

            $this->deleteUserPublications();

            $this->deleteUserWallet();

            $this->deleteUserStories();

            $this->deleteUserStoryViews();

            $this->deleteUserProducts();

            $this->deleteUserAds();

            $this->deleteUserJobs();

            $this->deleteUserNotifications();

            $this->deleteUserDevices();

            $this->resetUserChildAccounts();

            $this->deleteUserPayments();

            $this->deleteUserComments();

            $this->deleteUserSocialAccounts();

            $this->deleteUserSettings();

            $this->deleteUserBookmarks();

            $this->deleteUserBusinessAccount();

            $this->deleteSocialLinks();

            $this->deleteUserReports();

            $this->deleteUserHiddenMessages();

            $this->deleteUserHiddenChats();

            $this->deleteUserChatInvites();

            $this->deleteUserParticipatedChats();

            $this->deleteUserParticipatedGroups();

            $this->deleteUserGroups();

            $this->deleteUserAuthorshipRequests();

            if (! $this->userData->hasDefaultAvatar() && ! empty($this->userData->avatar)) {
                Storage::disk(static_storage_disk())->delete($this->userData->avatar);
                Storage::disk(static_storage_disk())->delete($this->userData->cover);
            }

            if (! $this->userData->hasDefaultCover() && ! empty($this->userData->cover)) {
                Storage::disk(static_storage_disk())->delete($this->userData->cover);
            }

            return $this->userData->delete();
        } catch (Throwable $th) {
            timeline_log($th->getMessage());
        }
    }

    private function unfollowAllUsers() {

        $followerIds  = $this->userData->followers()->pluck('follower_id');
        $followingIds = $this->userData->followings()->pluck('following_id');

        $this->userData->followers()->delete();
        $this->userData->followings()->delete();

        User::whereIn('id', $followerIds)->chunk(500, function ($users) {
            foreach ($users as $user) {
                $user->following_count = $user->followings()->where('status', FollowStatus::FOLLOWING)->count();
                $user->save();
            }
        });

        User::whereIn('id', $followingIds)->chunk(500, function ($users) {
            foreach ($users as $user) {
                $user->followers_count = $user->followers()->where('status', FollowStatus::FOLLOWING)->count();
                $user->save();
            }
        });
    }

    private function deleteUserPublications() {
        Post::with(['media'])->where('user_id', $this->userData->id)->chunk(500, function ($posts) {
            foreach ($posts as $post) {
                (new DeletePostAction($post))->execute();
            }
        });
    }

    private function deleteUserWallet() {
        if($this->userData->wallet) {
            $this->userData->wallet->transactions()->delete();
        }

        $this->userData->wallet()->delete();
    }

    private function deleteUserParticipatedChats() {
        $chats = Chat::where('type', ChatType::DIRECT)->whereHas('participants', function ($query) {
            $query->where('user_id', $this->userData->id);
        })->get();

        foreach ($chats as $chatData) {
            (new DeleteChatAction($chatData))->execute();
        }
    }

    private function deleteUserParticipatedGroups() {
        $chats = Chat::where('type', ChatType::GROUP)->whereHas('participants', function ($query) {
            $query->where('user_id', $this->userData->id);
        })->with(['group'])->get();

        foreach ($chats as $chatData) {
            if($chatData->group->user_id == $this->userData->id) {
                (new DeleteChatAction($chatData))->execute();
            }
            else {
                $chatData->participants()->where('user_id', $this->userData->id)->delete();

                $chatData->messages()->where('user_id', $this->userData->id)->chunk(500, function ($messages) {
                    foreach ($messages as $messageData) {
                        (new DeleteMessageAction($messageData))->execute();
                    }
                });
            }
        }
    }

    private function deleteUserGroups() {
        $groups = $this->userData->groups;

        foreach ($groups as $groupData) {
            (new DeleteGroupAction($groupData))->execute();

            // Delete related chat of the group if it exists.

            if($groupData->chat) {
                (new DeleteChatAction($groupData->chat))->execute();
            }
        }
    }

    private function deleteUserStories() {
        $storyData = $this->userData->story;

        if ($storyData) {
            if ($storyData->frames->isNotEmpty()) {
                foreach ($storyData->frames as $frameData) {
                    (new DeleteStoryFrameAction($frameData))->execute();
                }
            }

            $storyData->delete();
        }
    }

    private function deleteUserProducts() {
        $this->userData->products()->chunk(500, function ($products) {
            foreach ($products as $productData) {
                (new DeleteProductAction($productData))->execute();
            }
        });
    }

    private function deleteUserAds() {
        $this->userData->advertising()->chunk(500, function ($ads) {
            foreach ($ads as $adData) {
                (new DeleteAdAction($adData))->execute();
            }
        });
    }

    private function deleteUserJobs() {
        $this->userData->jobListings()->chunk(500, function ($jobs) {
            foreach ($jobs as $jobData) {
                (new DeleteJobAction($jobData))->execute();
            }
        });
    }

    private function deleteUserNotifications() {
        $this->userData->notifications()->delete();
    }

    private function deleteUserDevices() {
        $this->userData->devices()->delete();
    }

    private function resetUserChildAccounts() {
        $this->userData->linkedAccounts()->update([
            'owner_account_id' => null
        ]);
    }

    private function deleteUserSettings() {
        $this->userData->securitySettings()->delete();
        $this->userData->privacySettings()->delete();
        $this->userData->permitSettings()->delete();
        $this->userData->emailNotificationSettings()->delete();
        $this->userData->pushNotificationSettings()->delete();
        $this->userData->pins()->delete();
    }

    private function deleteUserPayments() {
        $this->userData->payments()->delete();
    }

    private function deleteUserStoryViews() {
        StoryView::where('user_id', $this->userData->id)->delete();
    }

    private function deleteUserComments() {
        $this->commentPosts = [];

        Comment::where('user_id', $this->userData->id)->chunk(500, function ($comments) {
            foreach ($comments as $commentData) {
                array_push($this->commentPosts, $commentData->post_id);

                (new DeleteCommentAction($commentData))->execute();
            }
        });

        // Accurate count of comments.

        Post::whereIn('id', array_unique($this->commentPosts))->chunk(500, function ($posts) {
            foreach ($posts as $postData) {
                $postData->comments_count = $postData->comments()->count();
                $postData->save();
            }
        });
    }

    private function deleteUserSocialAccounts() {
        $this->userData->socialAccounts()->delete();
    }

    private function deleteSocialLinks() {
        $this->userData->socialLinks()->delete();
    }

    private function deleteUserReports() {
        $this->userData->reports()->delete();

        Report::where('reporter_id', $this->userData->id)->delete();
    }

    private function deleteUserBookmarks() {
        Bookmark::where('user_id', $this->userData->id)->delete();
    }

    private function deleteUserBusinessAccount() {
        $this->userData->businessAccount()->delete();
    }

    private function deleteUserHiddenMessages() {
        HiddenMessage::where('user_id', $this->userData->id)->delete();
    }

    private function deleteUserHiddenChats() {
        HiddenChat::where('user_id', $this->userData->id)->delete();
    }

    private function deleteUserChatInvites() {
        ChatInvite::where('sender_id', $this->userData->id)
            ->orWhere('receiver_id', $this->userData->id)
            ->delete();
    }

    private function deleteUserAuthorshipRequests() {
        AuthorshipRequest::where('user_id', $this->userData->id)->delete();
    }

    private function deleteAllBlocks() {
        Block::where('blocker_id', $this->userData->id)
            ->orWhere('blocked_id', $this->userData->id)
            ->delete();
    }

    private function deleteAllMutes() {
        Mute::where('muter_id', $this->userData->id)
            ->orWhere('muted_id', $this->userData->id)
            ->delete();
    }
}
