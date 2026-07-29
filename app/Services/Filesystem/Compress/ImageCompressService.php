<?php

namespace App\Services\Filesystem\Compress;

use Exception;
use App\Constants\Filesystem;
use Illuminate\Http\UploadedFile;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Drivers\Gd\Encoders\PngEncoder;
use Intervention\Image\Drivers\Gd\Encoders\JpegEncoder;
use Intervention\Image\Drivers\Gd\Encoders\WebpEncoder;
use App\Services\Filesystem\Compress\Image\CompressedImage;

class ImageCompressService
{
    private string $imageEncoder = 'webp';

    private $compressionRate = 70;

    private $compressedImage;

    private $imageHeight;

    private $imageWidth;

    public function __construct()
    {
        $this->compressedImage = new CompressedImage();
        $this->imageEncoder = config('filesystems.image_encoder');
    }

    public function compressAndEncodeImage(UploadedFile $uploadedFile, bool $withPlaceholder = true)
    {
        try {
            $manager = new ImageManager(new Driver());
            $image = $manager->read($uploadedFile->getRealPath());

            $this->generateHighQualityImage($image);

            if ($withPlaceholder) {
                $this->generateLowQualityImage($image);
            }

            return $this->compressedImage;
        }
        
        catch (Exception $e) {
            // Add some logs here later.
        }
    }

    public function setCompressionRate(int $compressionRate)
    {
        $this->compressionRate = $compressionRate;

        return $this;
    }

    public function setEncoder(string $encoderName)
    {
        $this->imageEncoder = $encoderName;
    }

    private function generateHighQualityImage(ImageInterface $image) {
        $imageEncoder = $this->getEncoder();

        if($this->imageHeight && $this->imageWidth) {
            $image->cover($this->imageWidth, $this->imageHeight);
        }

        $imageData = $image->encode(new $imageEncoder($this->compressionRate));
            
        $this->compressedImage->setHighQuality($imageData);
    }

    private function generateLowQualityImage(ImageInterface $image) {
        $imageEncoder = $this->getEncoder();
        $placeholderWidth = Filesystem::IMAGE_PLACEHOLDER_WIDTH;
        $placeholderBlur = Filesystem::IMAGE_PLACEHOLDER_BLUR;

        if($this->imageHeight && $this->imageWidth) {
            $image->cover($placeholderWidth, $placeholderWidth);
        }

        else {
            $placeholderHeight = intval(($image->height() / $image->width()) * $placeholderWidth);

            $image->cover($placeholderWidth, $placeholderHeight);
        }

        $placeholderData = $image->blur($placeholderBlur)->encode(new $imageEncoder(10));

        $this->compressedImage->setLowQuality($placeholderData);
    }

    private function getEncoder()
    {
        $encoders = [
            'webp' => WebpEncoder::class,
            'png' => PngEncoder::class,
            'jpeg' => JpegEncoder::class
        ];

        return $encoders[$this->imageEncoder] ?? WebpEncoder::class;
    }
}
