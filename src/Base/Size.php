<?php
namespace Phgors\GoCaptcha\Base;

final class Size
{
    private int $width;
    private int $height;

    public function __construct(int $width, int $height)
    {
        if ($width <= 0 || $height <= 0) {
            throw new \InvalidArgumentException('尺寸必须为正数');
        }
        $this->width = $width;
        $this->height = $height;
    }

    public function getWidth(): int
    {
        return $this->width;
    }

    public function getHeight(): int
    {
        return $this->height;
    }
}
