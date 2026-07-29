<?php

namespace App\Http\Controllers\Admin\Story;

use App\Models\StoryFrame;
use App\Support\Views\Flash;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Actions\Story\DeleteStoryFrameAction;

class StoryController extends Controller
{
    public function index()
    {
        $stories = StoryFrame::active()->with(['story.user', 'media'])->paginate(10);

        return view('admin::stories.index.index', [
            'stories' => $stories
        ]);
    }

    public function show(int $frame_id)
    {
        $storyData = StoryFrame::active()->findOrFail($frame_id);

        return view('admin::stories.show.index', [
            'storyData' => $storyData
        ]);
    }

    public function destroy(int $frame_id)
    {
        $storyData = StoryFrame::active()->findOrFail($frame_id);

        (new DeleteStoryFrameAction($storyData))->execute();

        return redirect()->route('admin.stories.index')->with('flashMessage', (new Flash(content: __('admin/flash.story.delete_success')))->get());
    }
}
