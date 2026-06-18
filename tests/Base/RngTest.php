<?php
namespace Phgors\GoCaptcha\Tests\Base;

use Phgors\GoCaptcha\Base\Rng;
use Phgors\GoCaptcha\Base\RangeVal;
use PHPUnit\Framework\TestCase;

class RngTest extends TestCase
{
    public function test_get_int_within_range(): void
    {
        $rng = new Rng();
        for ($i = 0; $i < 100; $i++) {
            $v = $rng->getInt(5, 9);
            self::assertGreaterThanOrEqual(5, $v);
            self::assertLessThanOrEqual(9, $v);
        }
    }

    public function test_seeded_reproducible(): void
    {
        $a = new Rng(123);
        $b = new Rng(123);
        $seqA = [];
        $seqB = [];
        for ($i = 0; $i < 10; $i++) {
            $seqA[] = $a->getInt(0, 1000);
            $seqB[] = $b->getInt(0, 1000);
        }
        self::assertSame($seqA, $seqB);
    }

    public function test_range_uses_range_val(): void
    {
        $rng = new Rng(42);
        $v = $rng->range(new RangeVal(10, 20));
        self::assertGreaterThanOrEqual(10, $v);
        self::assertLessThanOrEqual(20, $v);
    }

    public function test_pick_returns_element_of_array(): void
    {
        $rng = new Rng(1);
        $items = ['a', 'b', 'c'];
        self::assertContains($rng->pick($items), $items);
    }

    public function test_shuffle_preserves_elements(): void
    {
        $rng = new Rng(7);
        $items = ['a', 'b', 'c', 'd'];
        $shuffled = $rng->shuffle($items);
        sort($shuffled);
        self::assertSame(['a', 'b', 'c', 'd'], $shuffled);
    }

    public function test_shuffle_is_deterministic_with_seed(): void
    {
        $items = ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h'];
        $a = new Rng(42);
        $b = new Rng(42);
        self::assertSame($a->shuffle($items), $b->shuffle($items));
    }

    public function test_two_instances_independent(): void
    {
        $a = new Rng(42);
        $b = new Rng(42);
        $seqA = [];
        for ($i = 0; $i < 5; $i++) {
            $seqA[] = $a->getInt(0, 1000);
        }
        $seqB = [];
        for ($i = 0; $i < 5; $i++) {
            $seqB[] = $b->getInt(0, 1000);
        }
        self::assertSame($seqA, $seqB);
    }
}
