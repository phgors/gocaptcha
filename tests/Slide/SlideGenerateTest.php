<?php
// tests/Slide/SlideGenerateTest.php
namespace Phgors\GoCaptcha\Tests\Slide;

use Phgors\GoCaptcha\Slide\SlideBuilder;
use Phgors\GoCaptcha\Slide\SlideCaptchaData;
use Phgors\GoCaptcha\Assets\DefaultAssets;
use PHPUnit\Framework\TestCase;

class SlideGenerateTest extends TestCase
{
    public function test_generate_returns_master_and_tile(): void
    {
        $graphSets = DefaultAssets::tileSets();
        if ($graphSets === []) {
            self::markTestSkipped('未提供拼图素材，跳过');
        }
        $graphs = array_map(function ($dir) {
            return new \Phgors\GoCaptcha\Slide\GraphImage(
                $dir . '/overlay.png',
                $dir . '/mask.png',
                $dir . '/shadow.png'
            );
        }, $graphSets);

        $captcha = SlideBuilder::make()
            ->setBackgrounds(DefaultAssets::backgrounds())
            ->setGraphs($graphs)
            ->build();

        $data = $captcha->generate();

        self::assertInstanceOf(SlideCaptchaData::class, $data);
        self::assertStringStartsWith('data:image/jpeg;base64,', $data->getMasterImage()->toBase64());
        self::assertStringStartsWith('data:image/png;base64,', $data->getTileImage()->toBase64());
        self::assertGreaterThan(0, $data->getBlock()->getX());
    }
}
