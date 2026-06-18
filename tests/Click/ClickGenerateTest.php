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
}
