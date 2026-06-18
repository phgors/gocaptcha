<?php
// src/Rotate/RotateOptions.php
namespace Phgors\GoCaptcha\Rotate;

use Phgors\GoCaptcha\Base\RangeVal;

final class RotateOptions
{
    private int $imageSquareSize;
    private int $thumbSquareSize;
    private RangeVal $rangeAngle;
    private float $thumbAlpha;

    public function __construct()
    {
        $this->imageSquareSize = 220;
        $this->thumbSquareSize = 150;
        $this->rangeAngle = new RangeVal(0, 360);
        $this->thumbAlpha = 1.0;
    }

    public function getImageSquareSize(): int { return $this->imageSquareSize; }
    public function getThumbSquareSize(): int { return $this->thumbSquareSize; }
    public function getRangeAngle(): RangeVal { return $this->rangeAngle; }
    public function getThumbAlpha(): float { return $this->thumbAlpha; }

    public function withImageSquareSize(int $v): self { $c = clone $this; $c->imageSquareSize = $v; return $c; }
    public function withThumbSquareSize(int $v): self { $c = clone $this; $c->thumbSquareSize = $v; return $c; }
    public function withRangeAngle(RangeVal $r): self { $c = clone $this; $c->rangeAngle = $r; return $c; }
    public function withThumbAlpha(float $v): self { $c = clone $this; $c->thumbAlpha = $v; return $c; }
}
