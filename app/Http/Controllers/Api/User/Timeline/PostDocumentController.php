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
        $postDocumentFile =  $request->file('document');

        $this->validatePostDocument([
            'document' => $postDocumentFile
        ]);

        $this->fetchOrInitializeDraftPost();

        if($this->draftPost->type->isTextified()) {
            if(! $this->draftPost->exists) {

                $this->draftPost->save();
            }

            if($this->draftPost->type->isTextified()) {
                $this->draftPost->type = PostType::DOCUMENT;
                $this->draftPost->save();
            }

            try {
                $documentData = $this->documentUploadService
                    ->setNamespace(Filesystem::mediaNamespace('documents'))
                    ->setStorageDisk($this->roundRobinService->getNextDisk())
                    ->upload($postDocumentFile);

                $this->draftPost->media()->create([
                    'source_path' => $documentData['document_path'],
                    'type' => MediaType::DOCUMENT,
                    'status' => MediaStatus::PROCESSED,
                    'disk' => $documentData['disk'],
                    'extension' => $postDocumentFile->getClientOriginalExtension(),
                    'mime' => $postDocumentFile->getClientMimeType(),
                    'size' => $postDocumentFile->getSize(),
                    'metadata' => [
                        'file_name' => $postDocumentFile->getClientOriginalName()
                    ]
                ]);

                return $this->responseSuccess([
                    'data' => [
                        'url' => storage_url($documentData['document_path'], $documentData['disk'])
                    ]
                ]);
            } catch (Exception $e) {
                return $this->responseError([
                    'message' => $e->getMessage(),
                    'errors' => [
                        'document' => [
                            $e->getMessage()
                        ]
                    ]
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }

        else{

            $errorMessage = __('post.validation.wrong_type_attachment', ['file_type' => __('labels.document')]);

            return $this->responseValidationError([
                'message' => $errorMessage,
                'errors' => [
                    'document' => [
                        $errorMessage
                    ]
                ]
            ]);
        }
    }
}
