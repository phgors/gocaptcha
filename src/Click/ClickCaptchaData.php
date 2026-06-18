<?php
// src/Click/ClickCaptchaData.php
namespace Phgors\GoCaptcha\Click;

use Phgors\GoCaptcha\ImageData\JpegImage;
use Phgors\GoCaptcha\ImageData\PngImage;

final class ClickCaptchaData
{
    /** @var Dot[] */
    private array $dots;
    private JpegImage $masterImage;
    private PngImage $thumbImage;

    /**
     * @param Dot[] $dots
     */
    public function __construct(array $dots, JpegImage $masterImage, PngImage $thumbImage)
    {
        $this->dots = $dots;
        $this->masterImage = $masterImage;
        $this->thumbImage = $thumbImage;
    }

    /** @return Dot[] */
    public function getDots(): array { return $this->dots; }

    public function getMasterImage(): JpegImage { return $this->masterImage; }
    public function getThumbImage(): PngImage { return $this->thumbImage; }

    /**
     * 仅暴露前端需要的字段（图像 base64），不暴露答案 dots。
     */
    public function toArray(): array
    {
        return [
            'masterImage' => $this->masterImage->toBase64(),
            'thumbImage'  => $this->thumbImage->toBase64(),
        ];
    }
}
