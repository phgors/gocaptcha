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
        self::assertStringStartsWith('data:image/jpeg;base64,', $data->getMasterImage()->toBase64());
        self::assertStringStartsWith('data:image/png;base64,', $data->getThumbImage()->toBase64());
        self::assertGreaterThanOrEqual(0, $data->getBlock()->getAngle());
        self::assertLessThanOrEqual(360, $data->getBlock()->getAngle());
    }
}
