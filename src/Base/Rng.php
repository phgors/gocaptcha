<?php
namespace Phgors\GoCaptcha\Base;

final class Rng
{
    private ?int $seed;
    private int $state;

    public function __construct(?int $seed = null)
    {
        $this->seed = $seed;
        if ($seed !== null) {
            $this->state = ($seed ^ 0x5DEECE66D) & 0xFFFFFFFF;
            if ($this->state === 0) {
                $this->state = 0x6D2B79F5;
            }
        }
    }

    private function next32(): int
    {
        $x = $this->state;
        $x ^= ($x << 13) & 0xFFFFFFFF;
        $x ^= ($x >> 17);
        $x ^= ($x << 5) & 0xFFFFFFFF;
        $this->state = $x & 0xFFFFFFFF;
        return $this->state;
    }

    public function getInt(int $min, int $max): int
    {
        if ($this->seed === null) {
            return random_int($min, $max);
        }
        $range = $max - $min;
        return $min + (int)($this->next32() % ($range + 1));
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
        $arr = array_values($items);
        for ($i = count($arr) - 1; $i > 0; $i--) {
            $j = $this->getInt(0, $i);
            [$arr[$i], $arr[$j]] = [$arr[$j], $arr[$i]];
        }
        return $arr;
    }
}
