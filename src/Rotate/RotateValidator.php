<?php
// src/Rotate/RotateValidator.php
namespace Phgors\GoCaptcha\Rotate;

final class RotateValidator
{
    public static function validate(int $angle, int $userAngle, int $padding): bool
    {
        $diff = abs($userAngle - $angle) % 360;
        if ($diff > 180) {
            $diff = 360 - $diff;
        }
        return $diff <= $padding;
    }
}
