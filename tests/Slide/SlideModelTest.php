<?php
// tests/Slide/SlideModelTest.php
namespace Phgors\GoCaptcha\Tests\Slide;

use Phgors\GoCaptcha\Slide\Block;
use Phgors\GoCaptcha\Slide\GraphImage;
use Phgors\GoCaptcha\Slide\SlideOptions;
use PHPUnit\Framework\TestCase;

class SlideModelTest extends TestCase
{
    public function test_block_holds_values(): void
    {
        $b = new Block(120, 80, 60, 60, 0);
        self::assertSame(120, $b->getX());
        self::assertSame(80, $b->getY());
        self::assertSame(60, $b->getWidth());
        self::assertSame(0, $b->getAngle());
    }

    public function test_graph_image_holds_paths(): void
    {
        $g = new GraphImage('/p/overlay.png', '/p/mask.png', '/p/shadow.png');
        self::assertSame('/p/overlay.png', $g->getOverlayPath());
        self::assertSame('/p/mask.png', $g->getMaskPath());
        self::assertSame('/p/shadow.png', $g->getShadowPath());
    }

    public function test_slide_options_defaults(): void
    {
        $o = new SlideOptions();
        self::assertSame(300, $o->getImageSize()->getWidth());
    }
}
