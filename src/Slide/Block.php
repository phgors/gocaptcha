<?php
// src/Slide/Block.php
namespace Phgors\GoCaptcha\Slide;

final class Block
{
    private int $x;
    private int $y;
    private int $width;
    private int $height;
    private int $angle;

    public function __construct(int $x, int $y, int $width, int $height, int $angle)
    {
        $this->x = $x;
        $this->y = $y;
        $this->width = $width;
        $this->height = $height;
        $this->angle = $angle;
    }

    public function getX(): int { return $this->x; }
    public function getY(): int { return $this->y; }
    public function getWidth(): int { return $this->width; }
    public function getHeight(): int { return $this->height; }
    public function getAngle(): int { return $this->angle; }

    public function toArray(): array
    {
        return ['x' => $this->x, 'y' => $this->y, 'width' => $this->width, 'height' => $this->height, 'angle' => $this->angle];
    }
}
