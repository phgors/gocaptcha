<?php
// src/Rotate/RotateGenerator.php
namespace Phgors\GoCaptcha\Rotate;

use Phgors\GoCaptcha\Base\Canvas;
use Phgors\GoCaptcha\Base\Color;
use Phgors\GoCaptcha\Base\Rng;
use Phgors\GoCaptcha\ImageData\PngImage;

final class RotateGenerator
{
    private RotateOptions $options;
    /** @var string[] */
    private array $backgrounds;
    private Rng $rng;

    /** @param string[] $backgrounds */
    public function __construct(RotateOptions $options, array $backgrounds, Rng $rng)
    {
        $this->options = $options;
        $this->backgrounds = $backgrounds;
        $this->rng = $rng;
    }

    public function generate(): RotateCaptchaData
    {
        $size = $this->options->getImageSquareSize();
        $thumbSize = $this->options->getThumbSquareSize();
        $angle = $this->rng->range($this->options->getRangeAngle());

        // 背景中心裁剪最大正方形
        $bgPath = $this->rng->pick($this->backgrounds);
        $bg = Canvas::fromPath($bgPath);
        $side = min($bg->getWidth(), $bg->getHeight());
        $offsetX = (int)(($bg->getWidth() - $side) / 2);
        $offsetY = (int)(($bg->getHeight() - $side) / 2);
        $square = new Canvas($side, $side);
        $square->copy($bg, 0, 0, $offsetX, $offsetY, $side, $side);
        $bg->destroy();

        // master：背景缩放 + 圆形遮罩（正立，不旋转）—— 对齐官方 go-captcha
        $master = new Canvas($size, $size);
        $master->copyResampled($square, 0, 0, 0, 0, $size, $size, $side, $side);
        $this->applyCircleAlpha($master, 1.0);

        // thumb：背景缩放 + 圆形遮罩 + 旋转 angle（用户旋转 thumb 对齐 master）
        $thumbBase = new Canvas($thumbSize, $thumbSize);
        $thumbBase->copyResampled($square, 0, 0, 0, 0, $thumbSize, $thumbSize, $side, $side);
        $this->applyCircleAlpha($thumbBase, $this->options->getThumbAlpha());
        $transparent = $thumbBase->allocateColor(new Color(0, 0, 0, 127));
        $rotated = $thumbBase->rotate((float) $angle, $transparent);
        $thumbBase->destroy();
        // imagerotate 会放大画布以容纳旋转图，裁回 thumbSize 中心（圆形旋转后仍居中）
        $rotW = $rotated->getWidth();
        $rc = (int)(($rotW - $thumbSize) / 2);
        $thumb = new Canvas($thumbSize, $thumbSize);
        $thumb->copy($rotated, 0, 0, max(0, $rc), max(0, $rc), $thumbSize, $thumbSize);
        $rotated->destroy();

        $square->destroy();

        $masterPng = new PngImage($master->releaseResource());
        $thumbPng = new PngImage($thumb->releaseResource());
        $block = new RotateBlock($angle);

        return new RotateCaptchaData($block, $masterPng, $thumbPng);
    }

    /**
     * 圆形遮罩：圆内按 alphaRatio 保留不透明度，圆外及边缘 1.5px 抗锯齿过渡为透明。
     */
    private function applyCircleAlpha(Canvas $canvas, float $alphaRatio): void
    {
        $side = $canvas->getWidth();
        $center = $side / 2;
        $radius = $center;
        $res = $canvas->getResource();
        imagealphablending($res, false);
        for ($y = 0; $y < $side; $y++) {
            for ($x = 0; $x < $side; $x++) {
                $dx = $x - $center;
                $dy = $y - $center;
                $dist = sqrt($dx * $dx + $dy * $dy);
                $rgb = imagecolorat($res, $x, $y);
                $r = (int)(($rgb >> 16) & 0xFF);
                $g = (int)(($rgb >> 8) & 0xFF);
                $b = (int)($rgb & 0xFF);
                $baseA = (int)(($rgb >> 24) & 0x7F);
                if ($dist > $radius) {
                    $baseA = 127;
                } elseif ($dist > $radius - 1.5) {
                    $baseA = 127 - (int)((1 - ($radius - $dist) / 1.5) * 127);
                }
                $finalA = 127 - (int)((127 - $baseA) * $alphaRatio);
                $finalA = max(0, min(127, $finalA));
                $col = imagecolorallocatealpha($res, $r, $g, $b, $finalA);
                if ($col !== false) {
                    imagesetpixel($res, $x, $y, $col);
                }
            }
        }
        imagealphablending($res, true);
    }
}
