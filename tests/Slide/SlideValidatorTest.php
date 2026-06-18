<?php
// tests/Slide/SlideValidatorTest.php
namespace Phgors\GoCaptcha\Tests\Slide;

use Phgors\GoCaptcha\Slide\Block;
use Phgors\GoCaptcha\Slide\SlideValidator;
use PHPUnit\Framework\TestCase;

class SlideValidatorTest extends TestCase
{
    public function test_validate_within_padding(): void
    {
        $block = new Block(100, 100, 60, 60, 0);
        self::assertTrue(SlideValidator::validate($block, 108, 105, 10));
    }

    public function test_validate_outside_padding(): void
    {
        $block = new Block(100, 100, 60, 60, 0);
        self::assertFalse(SlideValidator::validate($block, 130, 100, 10));
    }

    public function test_validate_accepts_array_block(): void
    {
        $block = ['x' => 100, 'y' => 100, 'width' => 60, 'height' => 60, 'angle' => 0];
        self::assertTrue(SlideValidator::validate($block, 108, 105, 10));
    }
}
