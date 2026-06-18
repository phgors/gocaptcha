<?php
// tests/Click/ClickBuilderTest.php
namespace Phgors\GoCaptcha\Tests\Click;

use Phgors\GoCaptcha\Click\ClickBuilder;
use Phgors\GoCaptcha\Click\ClickOptions;
use Phgors\GoCaptcha\Assets\DefaultAssets;
use PHPUnit\Framework\TestCase;

class ClickBuilderTest extends TestCase
{
    public function test_builder_make_returns_captcha(): void
    {
        $captcha = ClickBuilder::make()
            ->setBackgrounds(DefaultAssets::backgrounds())
            ->setFonts(DefaultAssets::fonts())
            ->setChars(DefaultAssets::chineseChars())
            ->build();
        self::assertInstanceOf(\Phgors\GoCaptcha\Click\ClickCaptcha::class, $captcha);
    }

    public function test_builder_make_without_backgrounds_throws(): void
    {
        $this->expectException(\Phgors\GoCaptcha\Exception\ResourceException::class);
        ClickBuilder::make()
            ->setFonts(DefaultAssets::fonts())
            ->setChars(DefaultAssets::chineseChars())
            ->build();
    }

    public function test_builder_requires_chars_count_above_range_max(): void
    {
        $this->expectException(\Phgors\GoCaptcha\Exception\GenerationException::class);
        ClickBuilder::make()
            ->setBackgrounds(DefaultAssets::backgrounds())
            ->setFonts(DefaultAssets::fonts())
            ->setChars(['丹', '王'])  // 少于 rangeLen.Max(5)
            ->build();
    }
}
