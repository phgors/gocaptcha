<?php
// src/Slide/SlideOptions.php
namespace Phgors\GoCaptcha\Slide;

use Phgors\GoCaptcha\Base\Size;
use Phgors\GoCaptcha\Base\RangeVal;

final class SlideOptions
{
    private Size $imageSize;
    private RangeVal $rangeGraphSize;
    private int $graphNumber;
    private bool $enableGraphVerticalRandom;
    private bool $regionMode;

    public function __construct()
    {
        $this->imageSize = new Size(300, 220);
        $this->rangeGraphSize = new RangeVal(50, 70);
        $this->graphNumber = 1;
        $this->enableGraphVerticalRandom = false;
        $this->regionMode = false;
    }

    public function getImageSize(): Size { return $this->imageSize; }
    public function getRangeGraphSize(): RangeVal { return $this->rangeGraphSize; }
    public function getGraphNumber(): int { return $this->graphNumber; }
    public function isEnableGraphVerticalRandom(): bool { return $this->enableGraphVerticalRandom; }
    public function isRegionMode(): bool { return $this->regionMode; }

    public function withImageSize(Size $s): self { $c = clone $this; $c->imageSize = $s; return $c; }
    public function withRangeGraphSize(RangeVal $r): self { $c = clone $this; $c->rangeGraphSize = $r; return $c; }
    public function withGraphNumber(int $n): self { $c = clone $this; $c->graphNumber = $n; return $c; }
    public function withEnableGraphVerticalRandom(bool $v): self { $c = clone $this; $c->enableGraphVerticalRandom = $v; return $c; }
    public function withRegionMode(bool $v): self { $c = clone $this; $c->regionMode = $v; return $c; }
}
