<?php
// src/Rotate/RotateGenerator.php
namespace Phgors\GoCaptcha\Rotate;

use Phgors\GoCaptcha\Base\Canvas;
use Phgors\GoCaptcha\Base\Color;
use Phgors\GoCaptcha\Base\Rng;
use Phgors\GoCaptcha\ImageData\JpegImage;
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

        $bgPath = $this->rng->pick($this->backgrounds);
        $bg = Canvas::fromPath($bgPath);
        $side = min($bg->getWidth(), $bg->getHeight());
        $offsetX = (int)(($bg->getWidth() - $side) / 2);
        $offsetY = (int)(($bg->getHeight() - $side) / 2);
        $square = new Canvas($side, $side);
        $square->copy($bg, 0, 0, $offsetX, $offsetY, $side, $side);
        $bg->destroy();

        $resized = new Canvas($size, $size);
        $resized->copyResampled($square, 0, 0, 0, 0, $size, $size, $side, $side);
        $square->destroy();

        $bgColor = $resized->allocateColor(Color::fromHex('#000000'));
        $rotated = $resized->rotate((float)$angle, $bgColor);
        $master = new Canvas($size, $size);
        $rx = (int)(($rotated->getWidth() - $size) / 2);
        $ry = (int)(($rotated->getHeight() - $size) / 2);
        $master->copy($rotated, 0, 0, max(0, $rx), max(0, $ry), $size, $size);

        $thumb = $this->makeCircularThumb($resized, $thumbSize);

        $rotated->destroy();
        $resized->destroy();

        $jpeg = new JpegImage($master->releaseResource());
        $png = new PngImage($thumb->releaseResource());
        $block = new RotateBlock($angle);

        return new RotateCaptchaData($block, $jpeg, $png);
    }

    private function makeCircularThumb(Canvas $master, int $thumbSize): Canvas
    {
        $w = $master->getWidth();
        $thumb = new Canvas($thumbSize, $thumbSize);
        $srcSide = $w;
        $thumb->copyResampled($master, 0, 0, 0, 0, $thumbSize, $thumbSize, $srcSide, $srcSide);

        $center = $thumbSize / 2;
        $radius = $center;
        for ($y = 0; $y < $thumbSize; $y++) {
            for ($x = 0; $x < $thumbSize; $x++) {
                $dx = $x - $center;
                $dy = $y - $center;
                $dist = sqrt($dx * $dx + $dy * $dy);
                if ($dist > $radius) {
                    $col = imagecolorallocatealpha($thumb->getResource(), 0, 0, 0, 127);
                    if ($col !== false) {
                        imagesetpixel($thumb->getResource(), $x, $y, $col);
                    }
                } elseif ($dist > $radius - 1.5) {
                    $a = 127 - (int)((1 - ($radius - $dist) / 1.5) * 127);
                    $rgb = imagecolorat($thumb->getResource(), $x, $y);
                    $r = (int)(($rgb >> 16) & 0xFF);
                    $g = (int)(($rgb >> 8) & 0xFF);
                    $b = (int)($rgb & 0xFF);
                    $col = imagecolorallocatealpha($thumb->getResource(), $r, $g, $b, max(0, min(127, $a)));
                    if ($col !== false) {
                        imagesetpixel($thumb->getResource(), $x, $y, $col);
                    }
                }
            }
        }
        return $thumb;
    }
}
