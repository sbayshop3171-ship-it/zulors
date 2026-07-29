<?php

namespace App\Http\Controllers\Api\User\Bookmark;

use App\Models\Post;
use App\Models\Product;
use App\Models\JobListing;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Traits\Http\Api\SupportsApiResponses;
use App\Http\Resources\User\Job\JobCollection;
use App\Http\Resources\User\Market\ProductCollection;
use App\Http\Resources\User\Timeline\TimelineResource;

class BookmarkController extends Controller
{
    use SupportsApiResponses;
    
    public function getBookmarks(Request $request)
    {
        $bookmarksType = $request->get('type');
        $bookmarksType = (in_array($bookmarksType, ['posts', 'jobs', 'products'])) ? $bookmarksType : 'posts';

        if($bookmarksType === 'posts') {
            return $this->getPostBookmarks();
        }

        else if($bookmarksType === 'jobs') {
            return $this->getJobBookmarks();
        }

        else if($bookmarksType === 'products') {
            return $this->getProductBookmarks();
        }

        return $this->responseSuccess([
            'data' => []
        ]);
    }

    private function getPostBookmarks()
    {
        $bookmarkedPosts = Post::timelineFormatPosts()->whereHas('bookmarks', function($query) {
            $query->where('user_id', me()->id);
        })->take(300)->get();

        return $this->responseSuccess([
            'data' => TimelineResource::collection($bookmarkedPosts)
        ]);
    }

    private function getJobBookmarks()
    {
        $bookmarkedJobs = JobListing::listable()->withRelations()->whereHas('bookmarks', function($query) {
            $query->where('user_id', me()->id);
        })->take(300)->get();

        return $this->responseSuccess([
            'data' => JobCollection::make($bookmarkedJobs)
        ]);
    }

    private function getProductBookmarks()
    {
        $bookmarkedProducts = Product::listable()->whereHas('bookmarks', function ($query) {
            $query->where('user_id', me()->id);
        })->withRelations()->latest('id')->take(1000)->get();
        
        return $this->responseSuccess([
            'data' => ProductCollection::make($bookmarkedProducts)
        ]);
    }
}
