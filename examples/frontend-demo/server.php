<?php
declare(strict_types=1);
// examples/frontend-demo/server.php
// 用法：php -S localhost:8000 -t public server.php
//   -t public 让内置 server 以 public/ 为文档根服务静态文件
//   server.php 作为路由器：处理 /api/*，其余请求 return false 交给内置 server

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/assemblers.php';

use Phgors\GoCaptcha\Assets\DefaultAssets;
use Phgors\GoCaptcha\Click\ClickBuilder;
use Phgors\GoCaptcha\Click\ClickValidator;
use Phgors\GoCaptcha\Rotate\RotateBuilder;
use Phgors\GoCaptcha\Rotate\RotateValidator;
use Phgors\GoCaptcha\Slide\GraphImage;
use Phgors\GoCaptcha\Slide\SlideBuilder;
use Phgors\GoCaptcha\Slide\SlideValidator;

// ── 缓存 Builder/Captcha（构造一次，反复 generate）─────────────────
function click_captcha(): \Phgors\GoCaptcha\Click\ClickCaptcha
{
    static $c = null;
    if ($c === null) {
        $c = ClickBuilder::make()
            ->setBackgrounds(DefaultAssets::backgrounds())
            ->setFonts(DefaultAssets::fonts())
            ->setChars(DefaultAssets::chineseChars())
            ->build();
    }
    return $c;
}

function slide_builder(): SlideBuilder
{
    static $b = null;
    if ($b === null) {
        $graphs = array_map(function ($dir) {
            return new GraphImage("$dir/overlay.png", "$dir/mask.png", "$dir/shadow.png");
        }, DefaultAssets::tileSets());
        $b = SlideBuilder::make()
            ->setBackgrounds(DefaultAssets::backgrounds())
            ->setGraphs($graphs);
    }
    return $b;
}

function rotate_captcha(): \Phgors\GoCaptcha\Rotate\RotateCaptcha
{
    static $c = null;
    if ($c === null) {
        $c = RotateBuilder::make()
            ->setBackgrounds(DefaultAssets::backgrounds())
            ->build();
    }
    return $c;
}

// ── 工具 ──────────────────────────────────────────────────────────
function json_out(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
}

function read_json_body(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

// ── 路由 ──────────────────────────────────────────────────────────
session_start();

$method = $_SERVER['REQUEST_METHOD'];
$path   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// 只处理 /api/*；其余交给内置 server 服务静态文件
if ($path === null || strpos($path, '/api/') !== 0) {
    return false;   // 让 php -S 服务 public/ 下的静态文件
}

try {
    // ── Click ─────────────────────────────────────────────────────
    if ($path === '/api/click' && $method === 'GET') {
        $data = click_captcha()->generate();
        $_SESSION['click_dots'] = array_map(fn($d) => $d->toArray(), $data->getDots());
        json_out(assemble_click($data));
        return;
    }
    if ($path === '/api/click/verify' && $method === 'POST') {
        $body = read_json_body();
        $points = $body['points'] ?? [];
        $dots   = $_SESSION['click_dots'] ?? [];
        $ok = ClickValidator::validate($dots, $points, 10);
        json_out(['ok' => $ok]);
        return;
    }

    // ── Slide ─────────────────────────────────────────────────────
    if ($path === '/api/slide' && $method === 'GET') {
        $data = slide_builder()->build()->generate();
        $_SESSION['slide_block'] = $data->getBlock()->toArray();
        json_out(assemble_slide($data, 5));
        return;
    }
    if ($path === '/api/slide/verify' && $method === 'POST') {
        $body = read_json_body();
        $block = $_SESSION['slide_block'] ?? [];
        $ok = SlideValidator::validate(
            $block,
            (int)($body['x'] ?? -1),
            (int)($body['y'] ?? -1),
            5
        );
        json_out(['ok' => $ok]);
        return;
    }

    // ── SlideRegion ───────────────────────────────────────────────
    if ($path === '/api/slide-region' && $method === 'GET') {
        $data = slide_builder()->buildRegion()->generate();
        $_SESSION['slide_region_block'] = $data->getBlock()->toArray();
        json_out(assemble_slide_region($data, 5, 5));
        return;
    }
    if ($path === '/api/slide-region/verify' && $method === 'POST') {
        $body = read_json_body();
        $block = $_SESSION['slide_region_block'] ?? [];
        $ok = SlideValidator::validate(
            $block,
            (int)($body['x'] ?? -1),
            (int)($body['y'] ?? -1),
            5
        );
        json_out(['ok' => $ok]);
        return;
    }

    // ── Rotate ────────────────────────────────────────────────────
    if ($path === '/api/rotate' && $method === 'GET') {
        $data = rotate_captcha()->generate();
        $_SESSION['rotate_angle'] = $data->getBlock()->getAngle();
        json_out(assemble_rotate($data, 150));
        return;
    }
    if ($path === '/api/rotate/verify' && $method === 'POST') {
        $body = read_json_body();
        $answer = (int)($_SESSION['rotate_angle'] ?? -1);
        $ok = RotateValidator::validate($answer, (int)($body['angle'] ?? -1), 8);
        json_out(['ok' => $ok]);
        return;
    }

    json_out(['ok' => false, 'error' => 'unknown endpoint'], 404);
} catch (\Throwable $e) {
    json_out(['ok' => false, 'error' => $e->getMessage()], 500);
}
