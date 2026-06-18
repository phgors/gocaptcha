<?php
namespace Phgors\GoCaptcha\Tests\Base;

use Phgors\GoCaptcha\Base\Size;
use Phgors\GoCaptcha\Base\Point;
use Phgors\GoCaptcha\Base\RangeVal;
use PHPUnit\Framework\TestCase;

class ValueObjectTest extends TestCase
{
    public function test_size_holds_dimensions(): void
    {
        $s = new Size(300, 220);
        self::assertSame(300, $s->getWidth());
        self::assertSame(220, $s->getHeight());
    }

    public function test_size_rejects_non_positive(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Size(0, 10);
    }

    public function test_point_holds_coordinates(): void
    {
        $p = new Point(5, 7);
        self::assertSame(5, $p->getX());
        self::assertSame(7, $p->getY());
    }

    public function test_range_val_holds_min_max(): void
    {
        $r = new RangeVal(2, 4);
        self::assertSame(2, $r->getMin());
        self::assertSame(4, $r->getMax());
    }

    public function test_range_val_rejects_min_greater_than_max(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RangeVal(5, 3);
    }

    public function test_range_val_random_within(): void
    {
        $r = new RangeVal(10, 20);
        for ($i = 0; $i < 50; $i++) {
            $v = $r->random();
            self::assertGreaterThanOrEqual(10, $v);
            self::assertLessThanOrEqual(20, $v);
        }
    }
}
