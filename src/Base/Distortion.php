<?php
// src/Base/Distortion.php
namespace Phgors\GoCaptcha\Base;

final class Distortion
{
    private Rng $rng;

    public function __construct(Rng $rng)
    {
        $this->rng = $rng;
    }

    public function drawCircles(Canvas $canvas, int $num, Color $color, int $maxRadius = 4): void
    {
        $c = $canvas->allocateColor($color);
        $w = $canvas->getWidth();
        $h = $canvas->getHeight();
        for ($i = 0; $i < $num; $i++) {
            $r = $this->rng->getInt(1, max(1, $maxRadius));
            $x = $this->rng->getInt(0, $w - 1);
            $y = $this->rng->getInt(0, $h - 1);
            imagefilledellipse($canvas->getResource(), $x, $y, $r * 2, $r * 2, $c);
        }
    }

    public function drawDots(Canvas $canvas, int $num, Color $color): void
    {
        $c = $canvas->allocateColor($color);
        $w = $canvas->getWidth();
        $h = $canvas->getHeight();
        for ($i = 0; $i < $num; $i++) {
            $canvas->setPixel($this->rng->getInt(0, $w - 1), $this->rng->getInt(0, $h - 1), $c);
        }
    }

    public function drawSlimLines(Canvas $canvas, int $num, Color $color): void
    {
        $c = $canvas->allocateColor($color);
        $w = $canvas->getWidth();
        $h = $canvas->getHeight();
        for ($i = 0; $i < $num; $i++) {
            $x1 = $this->rng->getInt(0, $w - 1);
            $y1 = $this->rng->getInt(0, $h - 1);
            $x2 = $this->rng->getInt(0, $w - 1);
            $y2 = $this->rng->getInt(0, $h - 1);
            imageline($canvas->getResource(), $x1, $y1, $x2, $y2, $c);
        }
    }
}
