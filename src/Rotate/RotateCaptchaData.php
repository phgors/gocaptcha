<?php
// src/Rotate/RotateCaptchaData.php
namespace Phgors\GoCaptcha\Rotate;

use Phgors\GoCaptcha\ImageData\PngImage;

final class RotateCaptchaData
{
    private RotateBlock $block;
    private PngImage $masterImage;
    private PngImage $thumbImage;

    public function __construct(RotateBlock $block, PngImage $masterImage, PngImage $thumbImage)
    {
        $this->block = $block;
        $this->masterImage = $masterImage;
        $this->thumbImage = $thumbImage;
    }

    public function getBlock(): RotateBlock { return $this->block; }
    public function getMasterImage(): PngImage { return $this->masterImage; }
    public function getThumbImage(): PngImage { return $this->thumbImage; }

    public function toArray(): array
    {
        return [
            'masterImage' => $this->masterImage->toBase64(),
            'thumbImage'  => $this->thumbImage->toBase64(),
        ];
    }
}
