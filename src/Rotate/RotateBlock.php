<?php
// src/Rotate/RotateBlock.php
namespace Phgors\GoCaptcha\Rotate;

final class RotateBlock
{
    private int $angle;

    public function __construct(int $angle)
    {
        $this->angle = $angle;
    }

    public function getAngle(): int { return $this->angle; }

    public function toArray(): array { return ['angle' => $this->angle]; }
}
