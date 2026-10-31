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
use App\Enums\Post\PostType;
use Illuminate\Http\Request;
use App\Constants\Filesystem;
use Illuminate\Http\Response;
use App\Enums\Media\MediaType;
use App\Enums\Media\MediaStatus;
use App\Http\Controllers\Controller;
use App\Traits\Http\Api\SupportsApiResponses;
use App\Services\Filesystem\Upload\ImageUploadService;
use App\Services\Filesystem\RoundRobin\RoundRobinService;
use App\Services\Filesystem\Base64Image\Base64ImageService;
use App\Exceptions\Timeline\Post\MaxImageUploadExceededException;
use App\Traits\Http\Controllers\Api\User\Timeline\ValidatesPostImage;
use App\Traits\Http\Controllers\Api\User\Timeline\InteractsWithDraftPost;

class PostImageController extends Controller
{
    use InteractsWithDraftPost,
        SupportsApiResponses,
        ValidatesPostImage;

    private $imageUploadService;
    private $roundRobinService;
    private $base64ImageService;

    public function __construct(ImageUploadService $imageUploadService, RoundRobinService $roundRobinService, Base64ImageService $base64ImageService)
    {
        $this->imageUploadService = $imageUploadService;
        $this->roundRobinService = $roundRobinService;
        $this->base64ImageService = $base64ImageService;
    }

    public function uploadImage(Request $request)
    {
        abort(response()->json(['message' => 'Photos must be uploaded through the signed direct R2 publication flow.', 'code' => 'direct_upload_required'], 409));
    }
}
