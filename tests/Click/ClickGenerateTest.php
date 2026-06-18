<?php
// tests/Click/ClickGenerateTest.php
namespace Phgors\GoCaptcha\Tests\Click;

use Phgors\GoCaptcha\Click\ClickBuilder;
use Phgors\GoCaptcha\Click\ClickCaptchaData;
use Phgors\GoCaptcha\Assets\DefaultAssets;
use PHPUnit\Framework\TestCase;

class ClickGenerateTest extends TestCase
{
    public function test_generate_returns_data_with_images_and_dots(): void
    {
        $captcha = ClickBuilder::make()
            ->setBackgrounds(DefaultAssets::backgrounds())
            ->setFonts(DefaultAssets::fonts())
            ->setChars(DefaultAssets::chineseChars())
            ->build();

        $data = $captcha->generate();

        self::assertInstanceOf(ClickCaptchaData::class, $data);
        self::assertNotEmpty($data->getDots());
        self::assertStringStartsWith('data:image/jpeg;base64,', $data->getMasterImage()->toBase64());
        self::assertStringStartsWith('data:image/png;base64,', $data->getThumbImage()->toBase64());

        foreach ($data->getDots() as $dot) {
            self::assertGreaterThan(0, $dot->getX());
            self::assertGreaterThan(0, $dot->getY());
        }
    }

    public function test_generate_deterministic_with_seed(): void
    {
        $build = function (int $seed) {
            return ClickBuilder::make()
                ->setBackgrounds(DefaultAssets::backgrounds())
                ->setFonts(DefaultAssets::fonts())
                ->setChars(DefaultAssets::chineseChars())
                ->setRng(new \Phgors\GoCaptcha\Base\Rng($seed))
                ->build()
                ->generate();
        };
        $a = $build(999);
        $b = $build(999);
        self::assertSame($a->getDots()[0]->getX(), $b->getDots()[0]->getX());
    }

    /**
     * 字符以 dot 为视觉中心、半径 ~字符半尺寸 的区域须完整落在画布内。
     * 回归：原实现把 imagettftext 基线坐标当作 Dot，导致字符边缘裁切、
     * 且与用户点击的视觉中心系统性偏差（>padding）使校验恒失败。
     */
    public function test_dots_are_character_centers_within_canvas(): void
    {
        $captcha = ClickBuilder::make()
            ->setBackgrounds(DefaultAssets::backgrounds())
            ->setFonts(DefaultAssets::fonts())
            ->setChars(DefaultAssets::chineseChars())
            ->build();

        $W = 300; $H = 220; // ClickOptions 默认 imageSize
        for ($i = 0; $i < 100; $i++) {
            $data = $captcha->generate();
            self::assertNotEmpty($data->getDots());
            foreach ($data->getDots() as $dot) {
                $half = (int) ceil($dot->getSize() * 0.55);
                self::assertGreaterThanOrEqual($half, $dot->getX(), "dot.x={$dot->getX()} 左侧可能裁切");
                self::assertLessThanOrEqual($W - $half, $dot->getX(), "dot.x={$dot->getX()} 右侧可能裁切");
                self::assertGreaterThanOrEqual($half, $dot->getY(), "dot.y={$dot->getY()} 顶部可能裁切");
                self::assertLessThanOrEqual($H - $half, $dot->getY(), "dot.y={$dot->getY()} 底部可能裁切");
            }
        }
    }
}
