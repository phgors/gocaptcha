<?php
// tests/Rotate/RotateModelTest.php
namespace Phgors\GoCaptcha\Tests\Rotate;

use Phgors\GoCaptcha\Rotate\RotateBlock;
use Phgors\GoCaptcha\Rotate\RotateOptions;
use PHPUnit\Framework\TestCase;

class RotateModelTest extends TestCase
{
    public function test_block_holds_angle(): void
    {
        self::assertSame(90, (new RotateBlock(90))->getAngle());
    }

    public function test_options_defaults(): void
    {
        $o = new RotateOptions();
        self::assertSame(220, $o->getImageSquareSize());
        self::assertSame(0, $o->getRangeAngle()->getMin());
        self::assertSame(360, $o->getRangeAngle()->getMax());
    }
}
