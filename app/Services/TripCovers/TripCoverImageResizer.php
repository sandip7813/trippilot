<?php

namespace App\Services\TripCovers;

use Illuminate\Support\Facades\Log;

class TripCoverImageResizer
{
    /**
     * @return non-empty-string|null JPEG bytes
     */
    public function toJpegThumbnail(string $imageBytes, int $width, int $height, int $quality = 82): ?string
    {
        if ($imageBytes === '') {
            return null;
        }

        $source = @imagecreatefromstring($imageBytes);

        if ($source === false) {
            Log::warning('Trip cover thumbnail could not be decoded.');

            return null;
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);

        if ($sourceWidth <= 0 || $sourceHeight <= 0) {
            imagedestroy($source);

            return null;
        }

        $thumbnail = imagecreatetruecolor($width, $height);

        if ($thumbnail === false) {
            imagedestroy($source);

            return null;
        }

        [$cropX, $cropY, $cropWidth, $cropHeight] = $this->centerCropSource(
            $sourceWidth,
            $sourceHeight,
            $width,
            $height,
        );

        imagecopyresampled(
            $thumbnail,
            $source,
            0,
            0,
            $cropX,
            $cropY,
            $width,
            $height,
            $cropWidth,
            $cropHeight,
        );

        imagedestroy($source);

        ob_start();
        $encoded = imagejpeg($thumbnail, null, $quality);
        imagedestroy($thumbnail);
        $jpegBytes = ob_get_clean();

        if ($encoded === false || ! is_string($jpegBytes) || $jpegBytes === '') {
            return null;
        }

        return $jpegBytes;
    }

    /**
     * @return array{0: int, 1: int, 2: int, 3: int}
     */
    private function centerCropSource(int $sourceWidth, int $sourceHeight, int $targetWidth, int $targetHeight): array
    {
        $sourceRatio = $sourceWidth / $sourceHeight;
        $targetRatio = $targetWidth / $targetHeight;

        if ($sourceRatio > $targetRatio) {
            $cropHeight = $sourceHeight;
            $cropWidth = (int) round($sourceHeight * $targetRatio);
            $cropX = (int) round(($sourceWidth - $cropWidth) / 2);
            $cropY = 0;

            return [$cropX, $cropY, $cropWidth, $cropHeight];
        }

        $cropWidth = $sourceWidth;
        $cropHeight = (int) round($sourceWidth / $targetRatio);
        $cropX = 0;
        $cropY = (int) round(($sourceHeight - $cropHeight) / 2);

        return [$cropX, $cropY, $cropWidth, $cropHeight];
    }
}
