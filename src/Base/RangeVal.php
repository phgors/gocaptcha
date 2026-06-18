<?php
namespace Phgors\GoCaptcha\Base;

final class RangeVal
{
    private int $min;
    private int $max;

    public function __construct(int $min, int $max)
    {
        if ($min > $max) {
            throw new \InvalidArgumentException('min 不能大于 max');
        }
        $this->min = $min;
        $this->max = $max;
    }

    public function getMin(): int
    {
        return $this->min;
    }

    public function getMax(): int
    {
        return $this->max;
    }

    public function random(): int
    {
        return mt_rand($this->min, $this->max);
    }
}
