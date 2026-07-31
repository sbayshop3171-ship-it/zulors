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

use App\Enums\Post\PostType;
use Illuminate\Http\Request;
use App\MediaApi\Giphy\Giphy;
use App\Enums\Media\MediaType;
use App\Enums\Media\MediaStatus;
use App\Http\Controllers\Controller;
use App\Traits\Http\Api\SupportsApiResponses;
use App\Traits\Http\Controllers\Api\User\Timeline\ValidatesPostGif;
use App\Traits\Http\Controllers\Api\User\Timeline\InteractsWithDraftPost;

class PostGifController extends Controller
{
    use SupportsApiResponses, ValidatesPostGif, InteractsWithDraftPost;

    public function createGif(Request $request)
    {
        $this->validatePostGif([
            'id' => $request->get('id', null)
        ]);

        $this->fetchOrInitializeDraftPost();
        $this->resetFailedDirectVideoDraftPost();
        $this->resetEmptyAttachmentDraftPost();

        if($this->draftPost->type->isTextified()) {

            if(! $this->draftPost->exists) {
                $this->draftPost->save();
            }

            if($this->draftPost->type->isTextified()) {
                $this->draftPost->type = PostType::GIF;
                $this->draftPost->save();
            }

            $this->draftPost->media()->create([
                'source_path' => Giphy::gifUrl($request->get('id', null)),
                'type' => MediaType::GIF,
                'status' => MediaStatus::PROCESSED,
                'disk' => Giphy::getDisk(),
                'extension' => 'gif',
                'mime' => 'image/gif',
                'size' => 0,
                'metadata' => [
                    'gif_id' => $request->get('id', null),
                    'preview_url' => Giphy::gifPreviewUrl($request->get('id', null))
                ]
            ]);

            return $this->responseSuccess([
                'data' => [
                    'url' => Giphy::gifUrl($request->get('id', null))
                ]
            ]);
        }

        else{
            return $this->responseValidationError([
                'message' => 'Can not attach to this type of post Gif.',
                'errors' => [
                    'gif' => [
                        'Can not attach to this type of post Gif.'
                    ]
                ]
            ]);
        }
    }
}
