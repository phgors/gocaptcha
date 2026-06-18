<?php
// tests/Click/ClickValidatorTest.php
namespace Phgors\GoCaptcha\Tests\Click;

use Phgors\GoCaptcha\Click\ClickValidator;
use Phgors\GoCaptcha\Click\Dot;
use PHPUnit\Framework\TestCase;

class ClickValidatorTest extends TestCase
{
    public function test_check_point_within_padding(): void
    {
        $dot = new Dot(0, 100, 100, 30);
        self::assertTrue(ClickValidator::checkPoint(108, 105, $dot, 10));
    }

    public function test_check_point_outside_padding(): void
    {
        $dot = new Dot(0, 100, 100, 30);
        self::assertFalse(ClickValidator::checkPoint(120, 100, $dot, 10));
    }

    public function test_validate_all_matched_in_any_order(): void
    {
        $dots = [new Dot(0, 50, 50, 30), new Dot(1, 200, 150, 30)];
        $points = [['x' => 201, 'y' => 149], ['x' => 49, 'y' => 51]];
        self::assertTrue(ClickValidator::validate($dots, $points, 10));
    }

    public function test_validate_fails_when_one_dot_not_matched(): void
    {
        $dots = [new Dot(0, 50, 50, 30), new Dot(1, 200, 150, 30)];
        $points = [['x' => 49, 'y' => 51], ['x' => 400, 'y' => 400]];
        self::assertFalse(ClickValidator::validate($dots, $points, 10));
    }

    public function test_validate_count_mismatch_fails(): void
    {
        $dots = [new Dot(0, 50, 50, 30)];
        self::assertFalse(ClickValidator::validate($dots, [], 10));
    }

    public function test_validate_accepts_array_dots(): void
    {
        $dots = [['index' => 0, 'x' => 50, 'y' => 50, 'size' => 30]];
        $points = [['x' => 55, 'y' => 48]];
        self::assertTrue(ClickValidator::validate($dots, $points, 10));
    }

    public function test_validate_accepts_numeric_indexed_points(): void
    {
        $dots = [new Dot(0, 100, 100, 30)];
        $points = [[105, 98]]; // [x, y]
        self::assertTrue(ClickValidator::validate($dots, $points, 10));
    }
}
