<?php
// src/Slide/SlideValidator.php
namespace Phgors\GoCaptcha\Slide;

final class SlideValidator
{
    /**
     * @param Block|array $block 答案（Block 对象或含 x/y 键的数组）
     */
    public static function validate($block, int $userX, int $userY, int $padding): bool
    {
        if (is_array($block)) {
            $bx = (int)($block['x'] ?? 0);
            $by = (int)($block['y'] ?? 0);
        } else {
            $bx = $block->getX();
            $by = $block->getY();
        }
        return abs($userX - $bx) <= $padding && abs($userY - $by) <= $padding;
    }
}
