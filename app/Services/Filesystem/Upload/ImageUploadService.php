<?php

namespace App\Services\Filesystem\Upload;

use Exception;
use App\Constants\Filesystem;
use Intervention\Image\ImageManager;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Drivers\Gd\Encoders\PngEncoder;
use Intervention\Image\Drivers\Gd\Encoders\JpegEncoder;
use Intervention\Image\Drivers\Gd\Encoders\WebpEncoder;
use App\Traits\Services\Filesystem\ThrowsUploadExceptions;
use App\Services\Filesystem\Abstract\AbstractUploadService;

class ImageUploadService extends AbstractUploadService
{
    use ThrowsUploadExceptions;

    private $image;
    private $manager;
    private string $imageDefaultExtension = 'webp';
    private string $imageEncoder = 'webp';
    private string $imageLQIPBase64 = '';

    public function __construct()
    {
        $this->imageEncoder = config('filesystems.image_encoder');
        $this->imageDefaultExtension = $this->extensionForEncoder($this->imageEncoder);

        return $this;
    }

    public function load(string $imagePath)
    {
        $this->manager = new ImageManager(new Driver());

        if (is_string($imagePath) && file_exists($imagePath)) {
            $this->image = $this->manager->read($imagePath)->orient();
        } else {
            throw new Exception("Invalid image source.");
        }

        return $this;
    }

    public function watermark(): self
    {
        try {
            if(config('brand.images_watermark_enabled')) {
                $this->image = $this->image->place(public_path(config('assets.watermark.local_path')), config('assets.watermark.image.position'), config('assets.watermark.image.padding'), config('assets.watermark.image.padding'));
            }

            return $this;
        }

        catch(Exception $e) {
            return $this;
        }
    }

    public function compress(int $rate = 90): self
    {
        // TODO
        // config('post.processing.image.compress_rate')
        // Pass it when uploading images for posts.

        $rate = max(1, min(100, $rate));

        $imageEncoder = $this->getImageEncoder();

        $this->image = $this->image->encode(new $imageEncoder($rate));

        return $this;
    }

    public function scaleDownToFit(int $maxWidth, int $maxHeight): self
    {
        $maxWidth = max(1, $maxWidth);
        $maxHeight = max(1, $maxHeight);

        $this->image->scaleDown($maxWidth, $maxHeight);

        return $this;
    }

    public function scaleTo1080x1920(): self
    {
        $canvas = $this->manager->create(1080, 1920)->fill('#000000');

        $this->image->scale(1080);

        $verticalPosition = (1920 - $this->image->height()) / 2;

        $this->image = $canvas->place($this->image, 'top', 0, (int) $verticalPosition);

        return $this;
    }

    public function crop(int $width, int $height): self
    {
        $this->image->cover($width, $height);

        return $this;
    }

    public function placeholder(): self
    {
        $this->image->scale(Filesystem::IMAGE_PLACEHOLDER_WIDTH)->blur(Filesystem::IMAGE_PLACEHOLDER_BLUR);

        return $this;
    }

    public function upload(): array
    {
       try {
            $uploadData = [
                'disk' => $this->storageDisk,
                'image_size' => $this->getImageSize(),
                'image_path' => $this->determineStoragePath($this->imageDefaultExtension)
            ];

            if (! empty($this->imageLQIPBase64)) {
                $uploadData['image_lqip'] = $this->imageLQIPBase64;
            }

            $imageUploadStatus = Storage::disk($this->storageDisk)->put($uploadData['image_path'], (string) $this->image, $this->getUploadOptions());

            if (empty($imageUploadStatus)) {
                $this->makeUploadException("Image upload on disk ({$this->storageDisk}) failed.");
            }

            return $uploadData;
        }

        catch(Exception $e) {
            $this->makeUploadException($e->getMessage());
        }
    }

    public function setImageEncoder(string $encoderName)
    {
        $this->imageEncoder = $encoderName;
        $this->imageDefaultExtension = $this->extensionForEncoder($encoderName);

        return $this;
    }

    private function getImageSize()
    {
        try {
            if (function_exists('mb_strlen')) {
                $size = mb_strlen($this->image, '8bit');
            }

            else {
                $size = strlen($this->image);
            }

            return $size;
        } catch (Exception $e) {
            return 0;
        }
    }

    private function getImageEncoder()
    {
        $encoders = [
            'webp' => WebpEncoder::class,
            'png' => PngEncoder::class,
            'jpeg' => JpegEncoder::class
        ];

        return $encoders[$this->imageEncoder] ?? WebpEncoder::class;
    }

    private function getUploadOptions(): array
    {
        $options = [
            'visibility' => 'public',
            'ContentType' => $this->contentTypeForEncoder($this->imageEncoder),
        ];

        if($cacheControl = config('media.cache.control')) {
            $options['CacheControl'] = $cacheControl;
        }

        return $options;
    }

    private function extensionForEncoder(string $encoderName): string
    {
        return $encoderName === 'jpeg' ? 'jpg' : $encoderName;
    }

    private function contentTypeForEncoder(string $encoderName): string
    {
        return $encoderName === 'jpeg' ? 'image/jpeg' : "image/{$encoderName}";
    }
}
