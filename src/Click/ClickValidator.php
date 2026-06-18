<?php
// src/Click/ClickValidator.php
namespace Phgors\GoCaptcha\Click;

final class ClickValidator
{
    public static function checkPoint(int $srcX, int $srcY, Dot $dot, int $padding): bool
    {
        return $srcX >= $dot->getX() - $padding
            && $srcX <= $dot->getX() + $padding
            && $srcY >= $dot->getY() - $padding
            && $srcY <= $dot->getY() + $padding;
    }

    /**
     * @param Dot[] $dots
     * @param array<int, array{x:int,y:int}> $userPoints
     */
    public static function validate(array $dots, array $userPoints, int $padding): bool
    {
        if (count($dots) !== count($userPoints)) {
            return false;
        }
        $matched = array_fill(0, count($dots), false);
        foreach ($userPoints as $pt) {
            $hit = false;
            foreach ($dots as $i => $dot) {
                if ($matched[$i]) {
                    continue;
                }
                if (self::checkPoint((int)$pt['x'], (int)$pt['y'], $dot, $padding)) {
                    $matched[$i] = true;
                    $hit = true;
                    break;
                }
            }
            if (!$hit) {
                return false;
            }
        }
        return true;
    }
}
