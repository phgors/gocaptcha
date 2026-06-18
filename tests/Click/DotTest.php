<?php
// tests/Click/DotTest.php
namespace Phgors\GoCaptcha\Tests\Click;

use Phgors\GoCaptcha\Click\Dot;
use Phgors\GoCaptcha\Click\ClickOptions;
use Phgors\GoCaptcha\Base\Size;
use Phgors\GoCaptcha\Base\RangeVal;
use PHPUnit\Framework\TestCase;

class DotTest extends TestCase
{
    public function test_dot_holds_values(): void
    {
        $d = new Dot(0, 100, 120, 32);
        self::assertSame(0, $d->getIndex());
        self::assertSame(100, $d->getX());
        self::assertSame(120, $d->getY());
        self::assertSame(32, $d->getSize());
    }

    public function test_click_options_defaults(): void
    {
        $o = new ClickOptions();
        self::assertSame(300, $o->getImageSize()->getWidth());
        self::assertSame(220, $o->getImageSize()->getHeight());
        self::assertSame(150, $o->getThumbSize()->getWidth());
        self::assertSame(40, $o->getThumbSize()->getHeight());
        self::assertFalse($o->isDisplayShadow());
    }

    public function test_click_options_with_chain(): void
    {
        $o = (new ClickOptions())
            ->withImageSize(new Size(400, 300))
            ->withRangeLen(new RangeVal(3, 6))
            ->withDisplayShadow(true);
        self::assertSame(400, $o->getImageSize()->getWidth());
        self::assertSame(6, $o->getRangeLen()->getMax());
        self::assertTrue($o->isDisplayShadow());
    }
}
