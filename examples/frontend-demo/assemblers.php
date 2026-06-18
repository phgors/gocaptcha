<?php
declare(strict_types=1);
// examples/frontend-demo/assemblers.php
//
// 把 phgors/gocaptcha 的 CaptchaData 组装成 go-captcha-jslib setData() 期望的字段。
// 这些函数只返回前端渲染所需的图像与初始位置/尺寸元数据；答案（dots/block.x/block.y/angle）
// 由 server.php 单独存 Session，绝不在此处输出。

use Phgors\GoCaptcha\Click\ClickCaptchaData;
use Phgors\GoCaptcha\Rotate\RotateCaptchaData;
use Phgors\GoCaptcha\Slide\SlideCaptchaData;

/** Click：主图 + 缩略图（后端字段已够用，仅重命名） */
function assemble_click(ClickCaptchaData $data): array
{
    return [
        'image' => $data->getMasterImage()->toBase64(),
        'thumb' => $data->getThumbImage()->toBase64(),
    ];
}

/** Slide（普通水平滑动）：thumbX=拼图初始绘制x（左侧小值）；thumbY=block.y（同行，y非秘密）。 */
function assemble_slide(SlideCaptchaData $data, int $thumbX): array
{
    $block = $data->getBlock();
    return [
        'image'       => $data->getMasterImage()->toBase64(),
        'thumb'       => $data->getTileImage()->toBase64(),
        'thumbX'      => $thumbX,
        'thumbY'      => $block->getY(),
        'thumbWidth'  => $block->getWidth(),
        'thumbHeight' => $block->getHeight(),
    ];
}

/** SlideRegion（区域拖拽）：初始位置置于左上角，远离右侧目标空洞。 */
function assemble_slide_region(SlideCaptchaData $data, int $thumbX, int $thumbY): array
{
    $block = $data->getBlock();
    return [
        'image'       => $data->getMasterImage()->toBase64(),
        'thumb'       => $data->getTileImage()->toBase64(),
        'thumbX'      => $thumbX,
        'thumbY'      => $thumbY,
        'thumbWidth'  => $block->getWidth(),
        'thumbHeight' => $block->getHeight(),
    ];
}

/** Rotate：thumb 初始正放（angle=0），thumbSize = thumbSquareSize。 */
function assemble_rotate(RotateCaptchaData $data, int $thumbSquareSize): array
{
    return [
        'image'     => $data->getMasterImage()->toBase64(),
        'thumb'     => $data->getThumbImage()->toBase64(),
        'angle'     => 0,
        'thumbSize' => $thumbSquareSize,
    ];
}
