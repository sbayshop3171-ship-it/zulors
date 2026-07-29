<?php

namespace App\Models\Traits\Bookmark;

use App\Models\Bookmark;

trait Bookmarkable
{
	public function bookmarks()
    {
        return $this->morphMany(Bookmark::class, 'bookmarkable', 'bookmarkable_type', 'bookmarkable_id', 'id');
    }

	public function addBookmark(int $userId)
    {
        $bookmark = $this->bookmarks()->firstOrCreate(['user_id' => $userId]);

        if($bookmark->wasRecentlyCreated) {
            $this->increment('bookmarks_count');
        }

        return $bookmark;
    }

	public function removeBookmark(int $userId)
    {
        $deletedCount = $this->bookmarks()->where('user_id', $userId)->delete();

        if($deletedCount) {
            $this->newQuery()->whereKey($this->getKey())->where('bookmarks_count', '>', 0)->decrement('bookmarks_count');
        }

        return $deletedCount;
    }

	public function isBookmarkedBy(int $userId)
    {
        if($this->relationLoaded('bookmarks')) {
            return $this->bookmarks->contains('user_id', $userId);
        }

        return $this->bookmarks()->where('user_id', $userId)->exists();
    }
}
