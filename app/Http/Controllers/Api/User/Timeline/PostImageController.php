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
        $postImageFile = $request->file('image');

        $this->validatePostImage([
            'image' => $postImageFile
        ]);

        $this->fetchOrInitializeDraftPost();
        $this->resetFailedDirectVideoDraftPost();
        $this->resetEmptyAttachmentDraftPost();

        if($this->draftPost->type->isTextified() || $this->draftPost->type->isImage()) {

            if(! $this->draftPost->exists) {
                $this->draftPost->save();
            }

            if($this->draftPost->type->isTextified()) {
                $this->draftPost->type = PostType::IMAGE;
                $this->draftPost->save();
            }

            try {
                if($this->draftPost->media->count() < config('post.max_images_count')) {
                    $imageStorageDisk = $this->roundRobinService->getNextDisk();
                    $imageNamespace = Filesystem::mediaNamespace('post/images');

                    $imageData = $this->imageUploadService
                        ->load($postImageFile->getRealPath())
                        ->setNamespace($imageNamespace)
                        ->setStorageDisk($imageStorageDisk)
                        ->scaleDownToFit(config('media.images.max_width'), config('media.images.max_height'))
                        ->watermark()
                        ->compress(config('post.processing.image.compress_rate'))
                        ->upload();

                    $LQIPBase64 = $this->base64ImageService->load($postImageFile->getRealPath())->getBase64();

                    $this->draftPost->media()->create([
                        'source_path' => $imageData['image_path'],
                        'type' => MediaType::IMAGE,
                        'status' => MediaStatus::PROCESSED,
                        'disk' => $imageData['disk'],
                        'extension' => $postImageFile->getClientOriginalExtension(),
                        'mime' => $postImageFile->getClientMimeType(),
                        'size' => $imageData['image_size'],
                        'lqip_base64' => $LQIPBase64,
                        'metadata' => []
                    ]);

                    $LQIPBase64Preview = $this->base64ImageService->load($postImageFile->getRealPath())->setScaleWidth(256)->setBlurRadius(0)->getBase64();

                    $this->draftPost->preview_lqip_base64 = $LQIPBase64Preview;
                    $this->draftPost->save();

                    return $this->responseSuccess([
                        'data' => [
                            'url' => storage_url($imageData['image_path'], $imageData['disk'])
                        ]
                    ]);
                }
                else{
                    throw new MaxImageUploadExceededException(__('post.validation.image_limit_reach', [
                        'max_images_count' => config('post.max_images_count')
                    ]));
                }

            } catch (MaxImageUploadExceededException $e) {
                return $this->responseValidationError([
                    'message' => $e->getMessage(),
                    'errors' => [
                        'image' => [
                            $e->getMessage()
                        ]
                    ]
                ]);
            } catch (Exception $e) {
                return $this->responseError([
                    'message' => $e->getMessage(),
                    'errors' => [
                        'image' => [
                            $e->getMessage()
                        ]
                    ]
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }

        else{
            $errorMessage = __('post.validation.wrong_type_attachment', ['file_type' => __('labels.image')]);

            return $this->responseValidationError([
                'message' => $errorMessage,
                'errors' => [
                    'image' => [
                        $errorMessage
                    ]
                ]
            ]);
        }
    }
}
