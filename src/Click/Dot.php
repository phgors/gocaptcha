<?php
// src/Click/Dot.php
namespace Phgors\GoCaptcha\Click;

final class Dot
{
    private int $index;
    private int $x;
    private int $y;
    private int $size;

    public function __construct(int $index, int $x, int $y, int $size)
    {
        $this->index = $index;
        $this->x = $x;
        $this->y = $y;
        $this->size = $size;
    }

    public function getIndex(): int { return $this->index; }
    public function getX(): int { return $this->x; }
    public function getY(): int { return $this->y; }
    public function getSize(): int { return $this->size; }

    public function toArray(): array
    {
        return ['index' => $this->index, 'x' => $this->x, 'y' => $this->y, 'size' => $this->size];
    }
}
