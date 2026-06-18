<?php
// tests/Slide/MaskProcessorTest.php
namespace Phgors\GoCaptcha\Tests\Slide;

use Phgors\GoCaptcha\Base\Canvas;
use Phgors\GoCaptcha\Slide\MaskProcessor;
use PHPUnit\Framework\TestCase;

class MaskProcessorTest extends TestCase
{
    public function test_apply_alpha_makes_black_mask_area_transparent(): void
    {
        $size = 8;
        $overlay = new Canvas($size, $size);
        $overlayCol = $overlay->allocateColor(\Phgors\GoCaptcha\Base\Color::fromHex('#ff0000'));
        imagefilledrectangle($overlay->getResource(), 0, 0, $size, $size, $overlayCol);

        // mask：上半白(255)下半黑(0)
        $mask = new Canvas($size, $size);
        $white = $mask->allocateColor(\Phgors\GoCaptcha\Base\Color::fromHex('#ffffff'));
        $black = $mask->allocateColor(\Phgors\GoCaptcha\Base\Color::fromHex('#000000'));
        imagefilledrectangle($mask->getResource(), 0, 0, $size, $size / 2, $white);
        imagefilledrectangle($mask->getResource(), 0, $size / 2, $size, $size, $black);

        $dest = new Canvas($size, $size);
        (new MaskProcessor())->applyAlpha($dest, $overlay, $mask);

        // 上半（mask 白）应不透明：alpha 期望接近 0
        self::assertLessThan(30, $dest->getAlphaAt(2, 1));
        // 下半（mask 黑）应接近全透明：alpha 期望接近 127
        self::assertGreaterThan(100, $dest->getAlphaAt(2, 6));

        $overlay->destroy(); $mask->destroy(); $dest->destroy();
    }
}
