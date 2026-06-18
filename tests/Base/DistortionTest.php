<?php
// tests/Base/DistortionTest.php
namespace Phgors\GoCaptcha\Tests\Base;

use Phgors\GoCaptcha\Base\Canvas;
use Phgors\GoCaptcha\Base\Color;
use Phgors\GoCaptcha\Base\Distortion;
use Phgors\GoCaptcha\Base\Rng;
use PHPUnit\Framework\TestCase;

class DistortionTest extends TestCase
{
    public function test_draw_circles_runs_without_error(): void
    {
        $c = new Canvas(150, 40);
        $d = new Distortion(new Rng(1));
        $d->drawCircles($c, 30, Color::fromHex('#cccccc'));
        self::assertIsArray($c->getRgbAt(10, 10));
        $c->destroy();
    }

    public function test_draw_lines_runs_without_error(): void
    {
        $c = new Canvas(150, 40);
        $d = new Distortion(new Rng(2));
        $d->drawSlimLines($c, 5, Color::fromHex('#999999'));
        $c->destroy();
    }

    public function test_draw_dots_runs_without_error(): void
    {
        $c = new Canvas(150, 40);
        $d = new Distortion(new Rng(3));
        $d->drawDots($c, 100, Color::fromHex('#666666'));
        $c->destroy();
    }
}
