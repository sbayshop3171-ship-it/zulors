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
use App\Enums\Media\MediaType;
use App\Enums\Media\MediaStatus;
use App\Http\Controllers\Controller;
use App\Traits\Http\Api\SupportsApiResponses;
use App\Services\Filesystem\Upload\AudioUploadService;
use App\Services\Filesystem\RoundRobin\RoundRobinService;
use App\Traits\Http\Controllers\Api\User\Timeline\ValidatesPostAudio;
use App\Traits\Http\Controllers\Api\User\Timeline\InteractsWithDraftPost;

class PostAudioController extends Controller
{
    use InteractsWithDraftPost,
        SupportsApiResponses, 
        ValidatesPostAudio;
    
    private $audioUploadService;
    private $roundRobinService;
    
    public function __construct(AudioUploadService $audioUploadService, RoundRobinService $roundRobinService)
    {
        $this->audioUploadService = $audioUploadService;
        $this->roundRobinService = $roundRobinService;
    }

    public function uploadAudio(Request $request)
    {
        abort(response()->json(['message' => 'Audio files must be uploaded through the signed direct R2 publication flow.', 'code' => 'direct_upload_required'], 409));
    }
}
