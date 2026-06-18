<?php
// tests/Slide/MaskProcessorTest.php
namespace Phgors\GoCaptcha\Tests\Slide;

use Phgors\GoCaptcha\Base\Canvas;
use Phgors\GoCaptcha\Base\Color;
use Phgors\GoCaptcha\Slide\MaskProcessor;
use PHPUnit\Framework\TestCase;

class MaskProcessorTest extends TestCase
{
    /**
     * 官方素材形状信息在 alpha 通道：alpha=0 形状内、alpha=127 形状外。
     * applyAlpha 应在形状内显示 bgCrop 内容，形状外透明。
     */
    public function test_apply_alpha_shows_bg_crop_inside_shape_and_transparent_outside(): void
    {
        $size = 8;
        $half = $size / 2;

        $bgCrop = new Canvas($size, $size);
        imagefilledrectangle($bgCrop->getResource(), 0, 0, $size - 1, $size - 1, $bgCrop->allocateColor(Color::fromHex('#ff0000')));

        $overlay = $this->filledAlphaCanvas($size, 127); // 全透明 overlay，不参与叠加

        $mask = new Canvas($size, $size);
        imagealphablending($mask->getResource(), false);
        imagefilledrectangle($mask->getResource(), 0, 0, $size - 1, $half - 1, imagecolorallocatealpha($mask->getResource(), 0, 0, 0, 0));
        imagefilledrectangle($mask->getResource(), 0, $half, $size - 1, $size - 1, imagecolorallocatealpha($mask->getResource(), 0, 0, 0, 127));
        imagealphablending($mask->getResource(), true);

        $dest = new Canvas($size, $size);
        (new MaskProcessor())->applyAlpha($dest, $bgCrop, $overlay, $mask);

        // 形状内（上半）：不透明，且显示 bgCrop 红色
        self::assertLessThan(30, $dest->getAlphaAt(2, 1));
        self::assertGreaterThan(200, $this->redAt($dest, 2, 1));
        // 形状外（下半）：透明
        self::assertGreaterThan(100, $dest->getAlphaAt(2, 6));

        $bgCrop->destroy();
        $overlay->destroy();
        $mask->destroy();
        $dest->destroy();
    }

    /**
     * cutHole 应在 mask 形状内按 shadow 叠阴影（变暗），形状外保持原样。
     */
    public function test_cut_hole_darkens_master_inside_shape(): void
    {
        $size = 8;
        $half = $size / 2;

        $master = new Canvas($size, $size);
        imagefilledrectangle($master->getResource(), 0, 0, $size - 1, $size - 1, $master->allocateColor(Color::fromHex('#ffffff')));

        $mask = new Canvas($size, $size);
        imagealphablending($mask->getResource(), false);
        imagefilledrectangle($mask->getResource(), 0, 0, $size - 1, $half - 1, imagecolorallocatealpha($mask->getResource(), 0, 0, 0, 0));
        imagefilledrectangle($mask->getResource(), 0, $half, $size - 1, $size - 1, imagecolorallocatealpha($mask->getResource(), 0, 0, 0, 127));
        imagealphablending($mask->getResource(), true);

        $shadow = new Canvas($size, $size);
        imagealphablending($shadow->getResource(), false);
        imagefilledrectangle($shadow->getResource(), 0, 0, $size - 1, $half - 1, imagecolorallocatealpha($shadow->getResource(), 0, 0, 0, 0));
        imagefilledrectangle($shadow->getResource(), 0, $half, $size - 1, $size - 1, imagecolorallocatealpha($shadow->getResource(), 0, 0, 0, 127));
        imagealphablending($shadow->getResource(), true);

        (new MaskProcessor())->cutHole($master, $mask, $shadow, 0, 0);

        self::assertLessThan(200, $this->redAt($master, 2, 1), '形状内应被阴影变暗');
        self::assertGreaterThan(250, $this->redAt($master, 2, 6), '形状外应保持原样');

        $master->destroy();
        $mask->destroy();
        $shadow->destroy();
    }

    private function filledAlphaCanvas(int $size, int $alpha): Canvas
    {
        $c = new Canvas($size, $size);
        imagealphablending($c->getResource(), false);
        imagefilledrectangle($c->getResource(), 0, 0, $size - 1, $size - 1, imagecolorallocatealpha($c->getResource(), 0, 0, 0, $alpha));
        imagealphablending($c->getResource(), true);
        return $c;
    }

    private function redAt(Canvas $c, int $x, int $y): int
    {
        $rgb = imagecolorat($c->getResource(), $x, $y);
        return ($rgb >> 16) & 0xFF;
    }
}
