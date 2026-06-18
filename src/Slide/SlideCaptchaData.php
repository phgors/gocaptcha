<?php
// src/Slide/SlideCaptchaData.php
namespace Phgors\GoCaptcha\Slide;

use Phgors\GoCaptcha\ImageData\JpegImage;
use Phgors\GoCaptcha\ImageData\PngImage;

final class SlideCaptchaData
{
    private Block $block;
    private JpegImage $masterImage;
    private PngImage $tileImage;

    public function __construct(Block $block, JpegImage $masterImage, PngImage $tileImage)
    {
        $this->block = $block;
        $this->masterImage = $masterImage;
        $this->tileImage = $tileImage;
    }

    public function getBlock(): Block { return $this->block; }
    public function getMasterImage(): JpegImage { return $this->masterImage; }
    public function getTileImage(): PngImage { return $this->tileImage; }

    public function toArray(): array
    {
        return [
            'masterImage' => $this->masterImage->toBase64(),
            'tileImage'   => $this->tileImage->toBase64(),
        ];
    }
}
