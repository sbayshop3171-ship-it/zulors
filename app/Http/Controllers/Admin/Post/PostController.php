<?php

namespace App\Http\Controllers\Admin\Post;

use App\Models\Post;
use App\Enums\Pin\PinType;
use App\Support\Views\Flash;
use Illuminate\Http\Request;
use App\Enums\Pin\PinContent;
use App\Http\Controllers\Controller;
use App\Actions\Post\DeletePostAction;

class PostController extends Controller
{
    public function index(Request $request)
    {
        $posts = Post::active()->with(['user'])->withCount('media')->latest()->paginate(10);

        return view('admin::posts.index.index', [
            'posts' => $posts
        ]);
    }

    public function show(int $postId)
    {
        $postData = $this->fetchPostDataOrFail($postId);

        $isPinnedGlobal = $postData->isPinnedGlobal();

        return view('admin::posts.show.index', [
            'postData' => $postData,
            'isPinnedGlobal' => $isPinnedGlobal
        ]);
    }

    public function destroy(int $postId)
    {
        $postData = Post::active()->findOrFail($postId);

        (new DeletePostAction($postData))->execute();
        
        $postData->user->decrementValue('publications_count', 1);

        return redirect()->route('admin.posts.index')->with('flashMessage', (new Flash(content: __('admin/flash.post.delete_success')))->get());
    }

    public function pin(int $postId)
    {
        $postData = $this->fetchPostDataOrFail($postId);
        $isPinnedGlobal = $postData->isPinnedGlobal();

        // Abort if the post is already pinned globally
        if($isPinnedGlobal) {
            abort(404);
        }

        // Pin the post globally
        $postData->pins()->create([
            'type' => PinType::GLOBAL,
            'user_id' => me()->id,
            'content_type' => PinContent::POST,
        ]);

        return redirect()->route('admin.posts.show', $postId)->with('flashMessage', (new Flash(content: __('admin/flash.post.pin_success')))->get());
    }

    public function unpin(int $postId)
    {
        $postData = $this->fetchPostDataOrFail($postId);

        // Unpin the post globally without checking if it is pinned globally.
        // Because only admin can unpin a post globally and it's pinned only once per post.

        $postData->pins()->where('type', PinType::GLOBAL)->delete();

        return redirect()->route('admin.posts.show', $postId)->with('flashMessage', (new Flash(content: __('admin/flash.post.unpin_success')))->get());
    }


    private function fetchPostDataOrFail(int $postId)
    {
        return Post::active()->with(['user', 'media'])->withCount('media')->findOrFail($postId);
    }
}
