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
use App\Services\Filesystem\RoundRobin\RoundRobinService;
use App\Services\Filesystem\Upload\DocumentUploadService;
use App\Traits\Http\Controllers\Api\User\Timeline\ValidatesPostDocument;
use App\Traits\Http\Controllers\Api\User\Timeline\InteractsWithDraftPost;

class PostDocumentController extends Controller
{
    use InteractsWithDraftPost,
        SupportsApiResponses,
        ValidatesPostDocument;

    private $documentUploadService;
    private $roundRobinService;


    public function __construct(DocumentUploadService $documentUploadService, RoundRobinService $roundRobinService) {
        $this->documentUploadService = $documentUploadService;
        $this->roundRobinService = $roundRobinService;
    }

    public function uploadDocument(Request $request) {
        abort(response()->json(['message' => 'Documents must be uploaded through the signed direct R2 publication flow.', 'code' => 'direct_upload_required'], 409));
    }
}
