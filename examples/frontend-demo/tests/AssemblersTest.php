<?php
declare(strict_types=1);
// examples/frontend-demo/tests/AssemblersTest.php
use PHPUnit\Framework\TestCase;
use Phgors\GoCaptcha\Click\ClickCaptchaData;
use Phgors\GoCaptcha\Click\Dot;
use Phgors\GoCaptcha\ImageData\JpegImage;
use Phgors\GoCaptcha\ImageData\PngImage;
use Phgors\GoCaptcha\Slide\Block;
use Phgors\GoCaptcha\Slide\SlideCaptchaData;
use Phgors\GoCaptcha\Rotate\RotateBlock;
use Phgors\GoCaptcha\Rotate\RotateCaptchaData;

final class AssemblersTest extends TestCase
{
    private function jpeg(): JpegImage
    {
        return new JpegImage(imagecreatetruecolor(10, 10));
    }

    private function png(): PngImage
    {
        return new PngImage(imagecreatetruecolor(10, 10));
    }

    public function test_assemble_click_returns_image_and_thumb_only(): void
    {
        $data = new ClickCaptchaData(
            [new Dot(0, 123, 456, 30)],
            $this->jpeg(),
            $this->png()
        );
        $out = assemble_click($data);
        $this->assertSame(['image', 'thumb'], array_keys($out));
        $this->assertStringStartsWith('data:image/jpeg;base64,', $out['image']);
        $this->assertStringStartsWith('data:image/png;base64,', $out['thumb']);
    }

    public function test_assemble_slide_maps_block_dims_and_uses_given_thumbX(): void
    {
        $data = new SlideCaptchaData(
            new Block(200, 150, 60, 60, 0),
            $this->jpeg(),
            $this->png()
        );
        $out = assemble_slide($data, 5);
        $this->assertSame(
            ['image', 'thumb', 'thumbX', 'thumbY', 'thumbWidth', 'thumbHeight'],
            array_keys($out)
        );
        $this->assertSame(5, $out['thumbX']);
        $this->assertSame(150, $out['thumbY']);
        $this->assertSame(60, $out['thumbWidth']);
        $this->assertSame(60, $out['thumbHeight']);
        $this->assertArrayNotHasKey('x', $out);
        $this->assertArrayNotHasKey('y', $out);
        $this->assertArrayNotHasKey('blockX', $out);
    }

    public function test_assemble_slide_region_uses_given_thumbX_and_thumbY(): void
    {
        $data = new SlideCaptchaData(
            new Block(220, 180, 55, 55, 0),
            $this->jpeg(),
            $this->png()
        );
        $out = assemble_slide_region($data, 5, 5);
        $this->assertSame(5, $out['thumbX']);
        $this->assertSame(5, $out['thumbY']);
        $this->assertSame(55, $out['thumbWidth']);
        $this->assertSame(55, $out['thumbHeight']);
    }

    public function test_assemble_rotate_has_zero_angle_and_thumbSize(): void
    {
        $data = new RotateCaptchaData(
            new RotateBlock(137),                     // 答案角度
            $this->png(),
            $this->png()
        );
        $out = assemble_rotate($data, 150);
        $this->assertSame(['image', 'thumb', 'angle', 'thumbSize'], array_keys($out));
        $this->assertSame(0, $out['angle']);
        $this->assertSame(150, $out['thumbSize']);
    }
}
