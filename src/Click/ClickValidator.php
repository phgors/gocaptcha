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
     * @param Dot[]|array[] $dots 答案（Dot 对象或含 x/y 键的数组）
     * @param array<int, array{x:int,y:int}|array{int,int}> $userPoints 用户点击坐标
     */
    public static function validate(array $dots, array $userPoints, int $padding): bool
    {
        if (count($dots) !== count($userPoints)) {
            return false;
        }
        $matched = array_fill(0, count($dots), false);
        foreach ($userPoints as $pt) {
            [$sx, $sy] = self::pointCoords($pt);
            $hit = false;
            foreach ($dots as $i => $dot) {
                if ($matched[$i]) {
                    continue;
                }
                [$dx, $dy] = self::dotCoords($dot);
                if (self::within($sx, $sy, $dx, $dy, $padding)) {
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

    private static function dotCoords($dot): array
    {
        if (is_array($dot)) {
            return [(int)($dot['x'] ?? 0), (int)($dot['y'] ?? 0)];
        }
        return [$dot->getX(), $dot->getY()];
    }

    private static function pointCoords($pt): array
    {
        if (isset($pt['x'])) {
            return [(int)$pt['x'], (int)($pt['y'] ?? 0)];
        }
        return [(int)($pt[0] ?? 0), (int)($pt[1] ?? 0)];
    }

    private static function within(int $sx, int $sy, int $dx, int $dy, int $padding): bool
    {
        return $sx >= $dx - $padding && $sx <= $dx + $padding
            && $sy >= $dy - $padding && $sy <= $dy + $padding;
    }
}
