<?php
// tests/Rotate/RotateGenerateTest.php
namespace Phgors\GoCaptcha\Tests\Rotate;

use Phgors\GoCaptcha\Rotate\RotateBuilder;
use Phgors\GoCaptcha\Rotate\RotateCaptchaData;
use Phgors\GoCaptcha\Assets\DefaultAssets;
use PHPUnit\Framework\TestCase;

class RotateGenerateTest extends TestCase
{
    public function test_generate_returns_master_and_thumb(): void
    {
        if (DefaultAssets::backgrounds() === []) {
            self::markTestSkipped('未提供背景素材');
        }
        $captcha = RotateBuilder::make()
            ->setBackgrounds(DefaultAssets::backgrounds())
            ->build();

        $data = $captcha->generate();

        self::assertInstanceOf(RotateCaptchaData::class, $data);
        self::assertStringStartsWith('data:image/png;base64,', $data->getMasterImage()->toBase64());
        self::assertStringStartsWith('data:image/png;base64,', $data->getThumbImage()->toBase64());
        self::assertGreaterThanOrEqual(0, $data->getBlock()->getAngle());
        self::assertLessThanOrEqual(360, $data->getBlock()->getAngle());
    }

    /**
     * master 应为圆形透明 PNG（对齐官方 go-captcha：master 正立圆形，不旋转）。
     * 回归：原实现 master 为旋转后的方形 JPEG，四角露黑色填充。
     */
    public function test_master_is_circular_png_without_black_corners(): void
    {
        if (DefaultAssets::backgrounds() === []) {
            self::markTestSkipped('未提供背景素材');
        }
        $captcha = RotateBuilder::make()
            ->setBackgrounds(DefaultAssets::backgrounds())
            ->build();

        $opaque = 0;
        for ($i = 0; $i < 3; $i++) {
            $data = $captcha->generate();
            $b64 = $data->getMasterImage()->toBase64();
            self::assertStringStartsWith('data:image/png;base64,', $b64);
            $bin = base64_decode(explode(',', $b64, 2)[1]);
            $im = imagecreatefromstring($bin);
            $w = imagesx($im);
            // 四角应落在圆形遮罩之外（透明）
            foreach ([[2, 2], [$w - 3, 2], [2, $w - 3], [$w - 3, $w - 3]] as [$cx, $cy]) {
                $c = imagecolorat($im, $cx, $cy);
                $a = ($c >> 24) & 0x7F;
                if ($a < 60) {
                    $opaque++;
                }
            }
            imagedestroy($im);
        }
        self::assertSame(0, $opaque, 'master 四角应为透明（圆形遮罩），仍存在不透明黑色角');
    }
}
