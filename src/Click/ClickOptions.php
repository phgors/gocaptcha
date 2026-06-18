<?php
// src/Click/ClickOptions.php
namespace Phgors\GoCaptcha\Click;

use Phgors\GoCaptcha\Base\Size;
use Phgors\GoCaptcha\Base\RangeVal;

final class ClickOptions
{
    private Size $imageSize;
    private Size $thumbSize;
    private RangeVal $rangeLen;
    private RangeVal $rangeVerifyLen;
    private RangeVal $rangeSize;
    /** @var string[] */
    private array $rangeColors;
    private bool $displayShadow;
    private string $shadowColor;
    private int $shadowOffsetX;
    private int $shadowOffsetY;

    public function __construct()
    {
        $this->imageSize = new Size(300, 220);
        $this->thumbSize = new Size(150, 40);
        $this->rangeLen = new RangeVal(4, 5);
        $this->rangeVerifyLen = new RangeVal(2, 4);
        $this->rangeSize = new RangeVal(26, 34);
        $this->rangeColors = ['#ffffff', '#ffeebb', '#aabbcc'];
        $this->displayShadow = false;
        $this->shadowColor = '#000000';
        $this->shadowOffsetX = 1;
        $this->shadowOffsetY = 1;
    }

    public function getImageSize(): Size { return $this->imageSize; }
    public function getThumbSize(): Size { return $this->thumbSize; }
    public function getRangeLen(): RangeVal { return $this->rangeLen; }
    public function getRangeVerifyLen(): RangeVal { return $this->rangeVerifyLen; }
    public function getRangeSize(): RangeVal { return $this->rangeSize; }
    /** @return string[] */
    public function getRangeColors(): array { return $this->rangeColors; }
    public function isDisplayShadow(): bool { return $this->displayShadow; }
    public function getShadowColor(): string { return $this->shadowColor; }
    public function getShadowOffsetX(): int { return $this->shadowOffsetX; }
    public function getShadowOffsetY(): int { return $this->shadowOffsetY; }

    public function withImageSize(Size $s): self { $c = clone $this; $c->imageSize = $s; return $c; }
    public function withThumbSize(Size $s): self { $c = clone $this; $c->thumbSize = $s; return $c; }
    public function withRangeLen(RangeVal $r): self { $c = clone $this; $c->rangeLen = $r; return $c; }
    public function withRangeVerifyLen(RangeVal $r): self { $c = clone $this; $c->rangeVerifyLen = $r; return $c; }
    public function withRangeSize(RangeVal $r): self { $c = clone $this; $c->rangeSize = $r; return $c; }
    /** @param string[] $colors */
    public function withRangeColors(array $colors): self { $c = clone $this; $c->rangeColors = $colors; return $c; }
    public function withDisplayShadow(bool $v): self { $c = clone $this; $c->displayShadow = $v; return $c; }
    public function withShadowColor(string $hex): self { $c = clone $this; $c->shadowColor = $hex; return $c; }
    public function withShadowOffset(int $x, int $y): self { $c = clone $this; $c->shadowOffsetX = $x; $c->shadowOffsetY = $y; return $c; }
}
