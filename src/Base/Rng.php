<?php
namespace Phgors\GoCaptcha\Base;

final class Rng
{
    private ?int $seed;
    private int $drawCount = 0;

    public function __construct(?int $seed = null)
    {
        $this->seed = $seed;
        if ($seed !== null) {
            mt_srand($seed);
        }
    }

    public function getInt(int $min, int $max): int
    {
        if ($this->seed === null) {
            return mt_rand($min, $max);
        }
        mt_srand($this->seed);
        for ($i = 0; $i < $this->drawCount; $i++) {
            mt_rand();
        }
        $this->drawCount++;
        return mt_rand($min, $max);
    }

    public function range(RangeVal $range): int
    {
        return $this->getInt($range->getMin(), $range->getMax());
    }

    public function pick(array $items)
    {
        if ($items === []) {
            throw new \InvalidArgumentException('不能从空数组选取');
        }
        $index = $this->getInt(0, count($items) - 1);
        return array_values($items)[$index];
    }

    public function shuffle(array $items): array
    {
        shuffle($items);
        return $items;
    }
}
