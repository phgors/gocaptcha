# 前端演示（go-captcha-jslib 对接）实现计划

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 在 `examples/frontend-demo/` 下构建端到端前端演示，用 `go-captcha-jslib`（CDN）对接本仓库 PHP 后端，展示点选/滑动/区域拖拽/旋转四种验证码的完整「生成→交互→校验」流程。

**Architecture:** 单页前端（纯 HTML + CDN 引入 go-captcha-jslib@1.0.9）⇄ PHP 内置 server（`server.php` 路由，复用根 `vendor/autoload.php`，答案存 Session）。字段映射逻辑抽成纯函数 `assemblers.php`（PHPUnit 覆盖），HTTP/前端交互用手动验证清单。

**Tech Stack:** PHP 8.2 + ext-gd（FreeType）、go-captcha-jslib@1.0.9（CDN unpkg）、原生 JS/CSS、PHPUnit 9（根 vendor）。

**Spec:** `docs/superpowers/specs/2026-06-19-frontend-demo-design.md`

---

## 文件结构

| 文件 | 职责 |
|------|------|
| Create: `examples/frontend-demo/assemblers.php` | 4 个纯函数：把 `CaptchaData` 组装成 go-captcha-jslib `setData()` 字段（可单测） |
| Create: `examples/frontend-demo/tests/bootstrap.php` | 测试引导：require 根 autoload + assemblers.php |
| Create: `examples/frontend-demo/tests/AssemblersTest.php` | PHPUnit 测试：字段名、映射值、答案不泄露 |
| Create: `examples/frontend-demo/server.php` | 路由入口：缓存 3 个 Builder、8 个 `/api/*` 端点、Session 存答案 |
| Create: `examples/frontend-demo/public/index.html` | 单页：CDN 引入、Tab 栏、四个面板 |
| Create: `examples/frontend-demo/public/app.js` | 前端逻辑：MODES 配置表驱动四种模式 |
| Create: `examples/frontend-demo/public/style.css` | 极简样式：布局、Tab、状态条 |
| Create: `examples/frontend-demo/README.md` | 运行说明与验证清单 |

> Commit message 一律用中文（AGENTS.md 规范）。若仓库未初始化 git，跳过 commit 步骤但保留语义节点。

---

## Task 1: assemblers.php 组装函数（TDD）

**Files:**
- Create: `examples/frontend-demo/assemblers.php`
- Create: `examples/frontend-demo/tests/bootstrap.php`
- Create: `examples/frontend-demo/tests/AssemblersTest.php`

- [ ] **Step 1: 写失败测试**

Create `examples/frontend-demo/tests/bootstrap.php`:

```php
<?php
// examples/frontend-demo/tests/bootstrap.php
$root = dirname(__DIR__, 3);
require $root . '/vendor/autoload.php';
require __DIR__ . '/../assemblers.php';
```

Create `examples/frontend-demo/tests/AssemblersTest.php`:

```php
<?php
// examples/frontend-demo/tests/AssemblersTest.php
use PHPUnit\Framework\TestCase;
use Phgors\GoCaptcha\Click\ClickCaptchaData;
use Phgors\GoCaptcha\Click\Dot;
use Phgors\GoCaptcha\ImageData\JpegImage;
use Phgors\GoCaptcha\ImageData\PngImage;
use Phgors\GoCaptcha\Slide\Block;
use Phgors\GoCaptcha\Slide\SlideCaptchaData;
use Phgors\GoCaptcha\Rotate\RotateBlock;
use Phgors\GoCaptcha\Rotate\RotateCaptchaData;

final class AssemblersTest extends TestCase
{
    private function jpeg(): JpegImage
    {
        return new JpegImage(imagecreatetruecolor(10, 10));
    }

    private function png(): PngImage
    {
        return new PngImage(imagecreatetruecolor(10, 10));
    }

    public function test_assemble_click_returns_image_and_thumb_only(): void
    {
        $data = new ClickCaptchaData(
            [new Dot(0, 123, 456, 30)],
            $this->jpeg(),
            $this->png()
        );
        $out = assemble_click($data);
        $this->assertSame(['image', 'thumb'], array_keys($out));
        $this->assertStringStartsWith('data:image/jpeg;base64,', $out['image']);
        $this->assertStringStartsWith('data:image/png;base64,', $out['thumb']);
    }

    public function test_assemble_slide_maps_block_dims_and_uses_given_thumbX(): void
    {
        $data = new SlideCaptchaData(
            new Block(200, 150, 60, 60, 0),   // 答案 x=200 y=150
            $this->jpeg(),
            $this->png()
        );
        $out = assemble_slide($data, 5);
        $this->assertSame(
            ['image', 'thumb', 'thumbX', 'thumbY', 'thumbWidth', 'thumbHeight'],
            array_keys($out)
        );
        $this->assertSame(5, $out['thumbX']);
        $this->assertSame(150, $out['thumbY']);      // = block.y（普通模式同行）
        $this->assertSame(60, $out['thumbWidth']);
        $this->assertSame(60, $out['thumbHeight']);
        $this->assertStringNotContainsString('200', $out['thumbX']); // 答案 x 不出现在前端字段
    }

    public function test_assemble_slide_region_uses_given_thumbX_and_thumbY(): void
    {
        $data = new SlideCaptchaData(
            new Block(220, 180, 55, 55, 0),
            $this->jpeg(),
            $this->png()
        );
        $out = assemble_slide_region($data, 5, 5);
        $this->assertSame(5, $out['thumbX']);
        $this->assertSame(5, $out['thumbY']);        // 初始角落，非答案 y
        $this->assertSame(55, $out['thumbWidth']);
        $this->assertSame(55, $out['thumbHeight']);
    }

    public function test_assemble_rotate_has_zero_angle_and_thumbSize(): void
    {
        $data = new RotateCaptchaData(
            new RotateBlock(137),                     // 答案角度
            $this->jpeg(),
            $this->png()
        );
        $out = assemble_rotate($data, 150);
        $this->assertSame(['image', 'thumb', 'angle', 'thumbSize'], array_keys($out));
        $this->assertSame(0, $out['angle']);          // 初始正放
        $this->assertSame(150, $out['thumbSize']);
    }
}
```

- [ ] **Step 2: 运行测试确认失败**

Run:
```bash
vendor/bin/phpunit --bootstrap examples/frontend-demo/tests/bootstrap.php examples/frontend-demo/tests/AssemblersTest.php
```
Expected: FAIL（`assemble_click` 等函数未定义 / assemblers.php 不存在）。

- [ ] **Step 3: 实现 assemblers.php**

Create `examples/frontend-demo/assemblers.php`:

```php
<?php
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

/**
 * Slide（普通水平滑动）：
 * thumbX = 拼图初始绘制 x（左侧小值，与答案 x 无关）；
 * thumbY = block.y（普通模式 y 不变，拼图与空洞同行，y 非秘密）。
 */
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
```

- [ ] **Step 4: 运行测试确认通过**

Run:
```bash
vendor/bin/phpunit --bootstrap examples/frontend-demo/tests/bootstrap.php examples/frontend-demo/tests/AssemblersTest.php
```
Expected: PASS（4 个测试全绿）。

- [ ] **Step 5: Commit**

```bash
git add examples/frontend-demo/assemblers.php examples/frontend-demo/tests/
git commit -m "feat: 添加前端演示的字段组装函数与单元测试"
```

---

## Task 2: server.php 路由（8 个 API 端点）

**Files:**
- Create: `examples/frontend-demo/server.php`

- [ ] **Step 1: 实现 server.php**

Create `examples/frontend-demo/server.php`:

```php
<?php
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

// ── 缓存 Builder（构造一次，反复 generate）──────────────────────────
function click_captcha(): ClickBuilder
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

function rotate_captcha(): RotateBuilder
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
        json_out(assemble_slide($data, 5));   // thumbX=5 左侧起始
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
        json_out(assemble_slide_region($data, 5, 5));  // 左上角起始
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
        json_out(assemble_rotate($data, 150));   // thumbSquareSize 默认 150
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
```

- [ ] **Step 2: 启动服务并用 curl 验证四个生成端点**

启动（在仓库根目录，另开终端保持运行）：
```bash
php -S localhost:8000 -t examples/frontend-demo/public examples/frontend-demo/server.php
```

验证 Click 返回 `image`/`thumb` 且无 `dots`：
```bash
curl -s http://localhost:8000/api/click | php -r '$d=json_decode(file_get_contents("php://stdin"),true); print_r(array_keys($d));'
```
Expected: 输出含 `image`、`thumb`，不含 `dots`/`index`/`x`（答案）。

验证 Slide 返回 6 字段：
```bash
curl -s http://localhost:8000/api/slide | php -r '$d=json_decode(file_get_contents("php://stdin"),true); print_r(array_keys($d));'
```
Expected: `image thumb thumbX thumbY thumbWidth thumbHeight`。

验证 SlideRegion、Rotate 同理（`/api/slide-region`、`/api/rotate`）。Rotate 应含 `angle=0` 与 `thumbSize=150`。

- [ ] **Step 3: 用 curl 验证四个校验端点返回 `{ok:bool}`**

先发一次 Click 生成（建立 Session），再故意用空 points 校验，应返回 `ok:false`：
```bash
curl -s -c /tmp/cj.txt http://localhost:8000/api/click > /dev/null
curl -s -b /tmp/cj.txt -X POST http://localhost:8000/api/click/verify -H "Content-Type: application/json" -d "{\"points\":[]}"
```
Expected: `{"ok":false}`（点数不匹配）。

对 Slide/Rotate 校验端点用错误坐标/角度 POST，确认返回 `{"ok":false}`：
```bash
curl -s -b /tmp/cj.txt -X POST http://localhost:8000/api/rotate/verify -H "Content-Type: application/json" -d "{\"angle\":-999}"
```
Expected: `{"ok":false}`。

- [ ] **Step 4: Commit**

```bash
git add examples/frontend-demo/server.php
git commit -m "feat: 添加前端演示的 PHP 路由与 8 个 API 端点"
```

---

## Task 3: 前端骨架 index.html + style.css

**Files:**
- Create: `examples/frontend-demo/public/index.html`
- Create: `examples/frontend-demo/public/style.css`

- [ ] **Step 1: 实现 index.html**

Create `examples/frontend-demo/public/index.html`:

```html
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>phgors/gocaptcha 前端演示 — go-captcha-jslib</title>
  <link rel="stylesheet" href="https://unpkg.com/go-captcha-jslib@1.0.9/dist/gocaptcha.global.css">
  <link rel="stylesheet" href="/style.css">
</head>
<body>
  <div class="wrap">
    <h1>phgors/gocaptcha 前端演示</h1>
    <p class="sub">用 <code>go-captcha-jslib</code> 对接 PHP 后端 · 点选 / 滑动 / 区域拖拽 / 旋转</p>

    <nav class="tabs">
      <button class="tab active" data-type="click">点选</button>
      <button class="tab" data-type="slide">滑动</button>
      <button class="tab" data-type="slide-region">区域拖拽</button>
      <button class="tab" data-type="rotate">旋转</button>
    </nav>

    <section class="panel active" id="panel-click">
      <div class="bar"><span class="status" data-status="idle">点击图中文字，完成后点确认</span><button class="refresh">刷新</button></div>
      <div class="captcha-box" id="captcha-click"></div>
    </section>

    <section class="panel" id="panel-slide">
      <div class="bar"><span class="status" data-status="idle">拖动滑块，把拼图移到缺口</span><button class="refresh">刷新</button></div>
      <div class="captcha-box" id="captcha-slide"></div>
    </section>

    <section class="panel" id="panel-slide-region">
      <div class="bar"><span class="status" data-status="idle">拖动拼图到缺口位置</span><button class="refresh">刷新</button></div>
      <div class="captcha-box" id="captcha-slide-region"></div>
    </section>

    <section class="panel" id="panel-rotate">
      <div class="bar"><span class="status" data-status="idle">旋转缩略图，使其与主图对齐</span><button class="refresh">刷新</button></div>
      <div class="captcha-box" id="captcha-rotate"></div>
    </section>
  </div>

  <script src="https://unpkg.com/go-captcha-jslib@1.0.9/dist/gocaptcha.global.js"></script>
  <script src="/app.js"></script>
</body>
</html>
```

- [ ] **Step 2: 实现 style.css**

Create `examples/frontend-demo/public/style.css`:

```css
* { box-sizing: border-box; }
body {
  margin: 0;
  font-family: -apple-system, "Segoe UI", "Microsoft YaHei", sans-serif;
  background: #f5f6f8;
  color: #222;
}
.wrap { max-width: 560px; margin: 32px auto; padding: 0 16px; }
h1 { font-size: 20px; margin: 0 0 4px; }
.sub { margin: 0 0 20px; color: #666; font-size: 13px; }
code { background: #eef; padding: 1px 5px; border-radius: 3px; }

.tabs { display: flex; gap: 8px; margin-bottom: 16px; }
.tab {
  padding: 8px 16px; border: 1px solid #d0d4dc; background: #fff;
  border-radius: 6px; cursor: pointer; font-size: 14px;
}
.tab.active { background: #2f6fed; color: #fff; border-color: #2f6fed; }

.panel { display: none; }
.panel.active { display: block; }

.bar {
  display: flex; align-items: center; justify-content: space-between;
  margin-bottom: 10px;
}
.status { font-size: 13px; padding: 4px 10px; border-radius: 4px; }
.status[data-status="idle"]    { color: #666; }
.status[data-status="loading"] { color: #888; }
.status[data-status="success"] { color: #fff; background: #2ea44f; }
.status[data-status="error"]   { color: #fff; background: #d73a49; }
.refresh {
  padding: 5px 12px; border: 1px solid #d0d4dc; background: #fff;
  border-radius: 5px; cursor: pointer; font-size: 13px;
}
.captcha-box {
  background: #fff; border: 1px solid #e3e5ea; border-radius: 8px;
  padding: 12px; min-height: 240px; display: flex; justify-content: center;
}
```

- [ ] **Step 3: 临时验证页面与静态服务**

服务已在 Task 2 启动。访问 `http://localhost:8000/`，确认：
- 页面渲染，标题正确。
- 四个 Tab 可切换（此时点选面板显示，其余隐藏）。
- 控制台无 404（CDN css/js、style.css 已加载；app.js 404 可忽略，下一任务创建）。

- [ ] **Step 4: Commit**

```bash
git add examples/frontend-demo/public/index.html examples/frontend-demo/public/style.css
git commit -m "feat: 添加前端演示页面骨架与样式"
```

---

## Task 4: 前端 app.js — 公共框架 + Click 模式

**Files:**
- Create: `examples/frontend-demo/public/app.js`

- [ ] **Step 1: 实现 app.js（公共框架 + Click，其余三种留空壳）**

Create `examples/frontend-demo/public/app.js`:

```js
// examples/frontend-demo/public/app.js
// 配置表驱动四种验证码模式。每种模式首次切到 Tab 时懒创建实例并加载。
(function () {
  "use strict";

  var GoCaptcha = window.GoCaptcha;

  // 各模式配置：构造器、API 路径、组件 config、confirm→verify 转换
  var MODES = {
    "click": {
      ctor: GoCaptcha.Click,
      api: "/api/click",
      cfg: { width: 300, height: 220, thumbWidth: 150, thumbHeight: 40 },
      // confirm(dots, reset) → POST body
      toBody: function (dots) {
        return { points: dots.map(function (d) { return { x: d.x, y: d.y }; }) };
      }
    },
    "slide": {
      ctor: GoCaptcha.Slide,
      api: "/api/slide",
      cfg: { width: 300, height: 220 },
      toBody: function (point) {
        return { x: point.x, y: point.y };
      }
    },
    "slide-region": {
      ctor: GoCaptcha.SlideRegion,
      api: "/api/slide-region",
      cfg: { width: 300, height: 220 },
      toBody: function (point) {
        return { x: point.x, y: point.y };
      }
    },
    "rotate": {
      ctor: GoCaptcha.Rotate,
      api: "/api/rotate",
      cfg: { width: 220, height: 220 },
      toBody: function (angle) {
        return { angle: angle };
      }
    }
  };

  var instances = {};   // type → 组件实例

  function $(sel, root) { return (root || document).querySelector(sel); }

  // 状态条
  function setStatus(type, status, text) {
    var span = $(".status", $("#panel-" + type));
    span.setAttribute("data-status", status);
    span.textContent = text;
  }

  // 请求生成接口并喂给组件
  function load(type) {
    var mode = MODES[type];
    setStatus(type, "loading", "加载中…");
    return fetch(mode.api)
      .then(function (r) {
        if (!r.ok) throw new Error("生成接口 HTTP " + r.status);
        return r.json();
      })
      .then(function (data) {
        instances[type].setData(data);
        setStatus(type, "idle", guideOf(type));
      })
      .catch(function (err) {
        console.error("[" + type + "] load failed", err);
        setStatus(type, "error", "请求失败，请点刷新重试");
      });
  }

  function guideOf(type) {
    return {
      "click": "点击图中文字，完成后点确认",
      "slide": "拖动滑块，把拼图移到缺口",
      "slide-region": "拖动拼图到缺口位置",
      "rotate": "旋转缩略图，使其与主图对齐"
    }[type];
  }

  // 请求校验接口
  function verify(type, payload, reset) {
    var mode = MODES[type];
    setStatus(type, "loading", "校验中…");
    fetch(mode.api + "/verify", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(mode.toBody(payload))
    })
      .then(function (r) {
        if (!r.ok) throw new Error("校验接口 HTTP " + r.status);
        return r.json();
      })
      .then(function (res) {
        if (res.ok) {
          setStatus(type, "success", "验证通过");
        } else {
          setStatus(type, "error", "验证失败，请重试");
          if (typeof reset === "function") reset();
        }
      })
      .catch(function (err) {
        console.error("[" + type + "] verify failed", err);
        setStatus(type, "error", "请求失败，请点刷新重试");
        if (typeof reset === "function") reset();
      });
  }

  // 绑定组件事件（按类型差异）
  function bindEvents(type, capt) {
    capt.setEvents({
      refresh: function () { load(type); },
      close: function () { /* 保留 */ },
      confirm: function (payload, reset) {
        console.log("[" + type + "] confirm payload:", payload);
        verify(type, payload, reset);
      }
    });
  }

  // 初始化某个模式（懒创建）
  function init(type) {
    if (instances[type]) {
      load(type);
      return;
    }
    var mode = MODES[type];
    var el = $("#captcha-" + type);
    var capt = new mode.ctor(mode.cfg);
    capt.mount(el);
    instances[type] = capt;
    bindEvents(type, capt);
    load(type);
  }

  // Tab 切换
  function activate(type) {
    document.querySelectorAll(".tab").forEach(function (t) {
      t.classList.toggle("active", t.dataset.type === type);
    });
    document.querySelectorAll(".panel").forEach(function (p) {
      p.classList.toggle("active", p.id === "panel-" + type);
    });
    init(type);
  }

  // 绑定 Tab 与刷新按钮
  document.querySelectorAll(".tab").forEach(function (t) {
    t.addEventListener("click", function () { activate(t.dataset.type); });
  });
  document.querySelectorAll(".refresh").forEach(function (btn) {
    btn.addEventListener("click", function () {
      var type = btn.closest(".panel").id.replace("panel-", "");
      load(type);
    });
  });

  // 默认进入点选
  activate("click");
})();
```

> 说明：`confirm(payload, reset)` 的 `payload` 对各模式不同（Click=dots 数组、Slide/SlideRegion=point 对象、Rotate=angle 数字），由 `MODES[type].toBody` 统一转成 POST body。Slide/SlideRegion/Rotate 的语义在 Task 5 通过 `console.log` 确认后如需调整换算，仅改 `toBody`。

- [ ] **Step 2: 浏览器验证 Click 端到端**

访问 `http://localhost:8000/`，在「点选」Tab：
1. 图像自动加载（主图 + 缩略图）。
2. 按缩略图提示点选图中文字，点组件内「确认」。
3. 控制台打印 `[click] confirm payload:` 形如 `[{x,y}, ...]`，确认元素含 `x`/`y`。
4. 正确点选 → 状态条变绿「验证通过」；故意点错 → 变红「验证失败」并 reset。

- [ ] **Step 3: Commit**

```bash
git add examples/frontend-demo/public/app.js
git commit -m "feat: 实现前端配置框架与点选模式端到端流程"
```

---

## Task 5: 验证并完善 Slide / SlideRegion / Rotate

**Files:**
- Modify: `examples/frontend-demo/public/app.js`（仅当语义假设不符时调整 `toBody`）

本任务核心是**验证 spec 第 7 节的语义假设**，必要时调整换算。组件实例化/事件绑定已由 Task 4 的通用框架完成，三种模式在 Tab 切换时即可工作；本任务只做「确认 + 必要修正」。

- [ ] **Step 1: 验证 Slide 的 confirm point 语义**

切到「滑动」Tab：
1. 图像加载，拼图初始在左侧（thumbX=5），空洞在右侧。
2. 拖动滑块把拼图对准缺口，点确认。
3. 观察控制台 `[slide] confirm payload:`，记录 `point` 结构。
4. 拖到正确位置 → 若状态条变绿，则 `point` 为**绝对坐标**（与后端 `block.x/y` 直接比较），无需改。
5. 若**每次都失败**（即使对得很准），则 `point` 为相对 `thumbX` 的偏移。改 `MODES.slide.toBody`：

```js
"slide": {
  ctor: GoCaptcha.Slide,
  api: "/api/slide",
  cfg: { width: 300, height: 220 },
  toBody: function (point, ctx) {
    return { x: point.x + ctx.thumbX, y: point.y + ctx.thumbY };
  }
}
```
并在 `verify()` 调用处把 `instances[type]` 上次 `setData` 的 `thumbX/thumbY` 作为 `ctx` 传入（见 Step 4 统一改造）。

- [ ] **Step 2: 验证 SlideRegion 的 confirm 签名（point vs dots）**

切到「区域拖拽」Tab：
1. 图像加载，拼图初始在左上角，空洞在右侧。
2. 拖动拼图到缺口，点确认。
3. 观察控制台 `[slide-region] confirm payload:`。
4. 若 `payload` 是单个对象（含 x/y）→ 当前 `toBody` 正确，对准则成功。
5. 若 `payload` 是数组 → README 与 TS 矛盾落地为数组。改 `MODES["slide-region"].toBody` 取首个元素：

```js
"slide-region": {
  ctor: GoCaptcha.SlideRegion,
  api: "/api/slide-region",
  cfg: { width: 300, height: 220 },
  toBody: function (payload) {
    var p = Array.isArray(payload) ? payload[0] : payload;
    return { x: p.x, y: p.y };
  }
}
```

- [ ] **Step 3: 验证 Rotate 的 confirm angle 语义**

切到「旋转」Tab：
1. 图像加载，缩略图初始正放（angle=0），主图为旋转后的背景。
2. 旋转缩略图与主图对齐，点确认。
3. 观察控制台 `[rotate] confirm payload:`，确认是数字角度。
4. 对齐后状态条变绿则语义正确（后端 `RotateValidator` 已处理 `%360`）。
5. 若始终失败，检查角度方向（顺/逆时针），必要时 `toBody` 用 `(360 - angle)` 折返。

- [ ] **Step 4: （仅当 Step 1 触发偏移换算时）统一改造 toBody 传 ctx**

若 Step 1 判定 Slide 需要偏移换算，则让 `load()` 缓存上次 `setData` 的元数据，并在 `verify()` 传入：

在 `load()` 的 `.then(function (data) { instances[type].setData(data); ... })` 中加 `instances[type].__meta = data;`；

改 `verify()` 与 `confirm`：
```js
confirm: function (payload, reset) {
  console.log("[" + type + "] confirm payload:", payload);
  verify(type, payload, reset);
}
// verify 内：
body: JSON.stringify(mode.toBody(payload, instances[type].__meta))
```
若未触发偏移换算，跳过本步。

- [ ] **Step 5: 四模式回归**

依次切到四个 Tab，每个都跑一遍「正确→成功」「错误→失败+reset」「刷新→换新图」，确认全部正常。

- [ ] **Step 6: Commit（如有调整）**

```bash
git add examples/frontend-demo/public/app.js
git commit -m "fix: 修正验证码前端 confirm 语义换算"
```
（无调整则跳过此 commit。）

---

## Task 6: README + 最终集成验证

**Files:**
- Create: `examples/frontend-demo/README.md`

- [ ] **Step 1: 写 README**

Create `examples/frontend-demo/README.md`:

````markdown
# phgors/gocaptcha 前端演示

用 [go-captcha-jslib](https://www.npmjs.com/package/go-captcha-jslib)（CDN）对接本仓库 PHP 后端，演示点选 / 滑动 / 区域拖拽 / 旋转四种验证码的完整流程。

## 运行

前置：仓库根目录已 `composer install`；PHP ≥ 7.4 启用 `ext-gd`（建议 FreeType）。

```bash
# 在仓库根目录执行
php -S localhost:8000 -t examples/frontend-demo/public examples/frontend-demo/server.php
```

浏览器打开 <http://localhost:8000>，切换顶部 Tab 体验四种验证码。

## 结构

| 文件 | 作用 |
|------|------|
| `server.php` | PHP 路由：8 个 `/api/*` 端点（4 生成 + 4 校验），答案存 Session |
| `assemblers.php` | 把 `CaptchaData` 组装成 go-captcha-jslib `setData()` 字段 |
| `public/index.html` | 单页：CDN 引入、Tab、四个面板 |
| `public/app.js` | 配置表驱动的四种模式交互逻辑 |
| `public/style.css` | 极简样式 |
| `tests/` | assemblers 的 PHPUnit 测试 |

## 跑测试

```bash
vendor/bin/phpunit --bootstrap examples/frontend-demo/tests/bootstrap.php examples/frontend-demo/tests/
```

## 数据流

```
进入 Tab → GET /api/{type} → setData(图像+元数据) → 用户交互
        → confirm → POST /api/{type}/verify → {ok} → 成功/失败(reset)
```

答案只存在 PHP Session，不回传前端。生成接口仅返回 go-captcha-jslib 渲染所需字段。
````

- [ ] **Step 2: 执行 spec 第 8 节的运行与验证清单**

逐项核对（spec `2026-06-19-frontend-demo-design.md` 第 8 节）：
1. `composer install` 已完成（vendor 存在）。
2. `php -S localhost:8000 -t examples/frontend-demo/public examples/frontend-demo/server.php` 正常启动。
3. 四个 Tab：图像加载、正确交互→成功、错误交互→失败+reset、刷新→换图，全部通过。
4. Network：生成接口返回前端字段、不含答案；校验接口返回 `{ok}`。
5. Session 存有答案、未泄露前端（curl 已在 Task 2 验证，复查即可）。

- [ ] **Step 3: 跑一次完整 PHPUnit**

```bash
vendor/bin/phpunit --bootstrap examples/frontend-demo/tests/bootstrap.php examples/frontend-demo/tests/
```
Expected: 4 个测试全绿。

- [ ] **Step 4: Commit**

```bash
git add examples/frontend-demo/README.md
git commit -m "docs: 添加前端演示运行说明与验证清单"
```

---

## Self-Review 备忘

- **Spec 覆盖**：四种模式字段映射（Task 1+2）、8 端点（Task 2）、前端 Tab/面板/状态/错误处理（Task 3+4）、第 7 节语义验证（Task 5）、运行验证清单（Task 6）均有对应任务。✅
- **类型/命名一致**：`assemble_*` ↔ server 调用、`MODES` 的 `toBody` ↔ confirm payload、`thumbX/thumbY/thumbWidth/thumbHeight/thumbSize/angle` ↔ spec 第 5 节，命名全程一致。✅
- **占位符**：无 TBD/TODO；所有代码步骤含完整可执行代码。✅
