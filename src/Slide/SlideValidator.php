<?php
// src/Slide/SlideValidator.php
namespace Phgors\GoCaptcha\Slide;

final class SlideValidator
{
    public static function validate(Block $block, int $userX, int $userY, int $padding): bool
    {
        return abs($userX - $block->getX()) <= $padding && abs($userY - $block->getY()) <= $padding;
    }
}
