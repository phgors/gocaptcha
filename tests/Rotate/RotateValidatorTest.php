<?php
// tests/Rotate/RotateValidatorTest.php
namespace Phgors\GoCaptcha\Tests\Rotate;

use Phgors\GoCaptcha\Rotate\RotateValidator;
use PHPUnit\Framework\TestCase;

class RotateValidatorTest extends TestCase
{
    public function test_within_padding(): void
    {
        self::assertTrue(RotateValidator::validate(100, 108, 10));
    }

    public function test_wraparound_0_360(): void
    {
        self::assertTrue(RotateValidator::validate(358, 2, 10));
        self::assertTrue(RotateValidator::validate(2, 358, 10));
    }

    public function test_outside_padding(): void
    {
        self::assertFalse(RotateValidator::validate(100, 130, 10));
    }
}
