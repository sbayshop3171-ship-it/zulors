<?php
namespace App\Services\Filesystem\Compress\Image;

use Exception;

class CompressedImage
{
    private $highQuality;
    private $lowQuality;

    public function setHighQuality($highQuality)
    {
        $this->highQuality = $highQuality;
    }

    public function setLowQuality($lowQuality)
    {
        $this->lowQuality = $lowQuality;
    }

    public function getHighQuality()
    {
        return $this->highQuality;
    }

    public function getLowQuality()
    {
        return $this->lowQuality;
    }

    public function getHighQualitySize()
    {
        return $this->getImageSize($this->highQuality);
    }

    public function getLowQualitySize()
    {
        return $this->getImageSize($this->lowQuality);
    }

    private function getImageSize($encodedImageData)
    {
        try {
            if (function_exists('mb_strlen')) {
                $size = mb_strlen($encodedImageData, '8bit');
            }
            
            else {
                $size = strlen($encodedImageData);
            }

            return $size;
        } catch (Exception $e) {
            return 0;
        }
    }
}