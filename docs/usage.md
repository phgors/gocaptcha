# phgors/gocaptcha 使用文档

> PHP 行为验证码库（点选 / 滑动 / 旋转），[go-captcha](https://github.com/wenlng/go-captcha) 生态的 PHP 后端实现。
> 基于 GD 扩展，开箱即用，纯无状态，框架无关。

## 目录

- [环境要求](#环境要求)
- [安装](#安装)
- [核心概念](#核心概念)
- [快速开始](#快速开始)
- [点选验证码（Click）](#点选验证码click)
- [滑动验证码（Slide）](#滑动验证码slide)
- [旋转验证码（Rotate）](#旋转验证码rotate)
- [配置参考](#配置参考)
- [图像输出](#图像输出)
- [素材资源](#素材资源)
- [随机数与可复现性](#随机数与可复现性)
- [异常处理](#异常处理)
- [与前端对接](#与前端对接)
- [框架集成示例](#框架集成示例)
- [常见问题](#常见问题)
- [API 速查表](#api-速查表)

---

## 环境要求

| 项 | 要求 |
|----|------|
| PHP | >= 7.4 |
| 扩展 | `ext-gd`（必须） |
| GD 编译选项 | 建议启用 **FreeType**（中文字符渲染）、JPEG、PNG（一般默认都有） |

检测环境：

```bash
php -r "var_dump(extension_loaded('gd'));"
php -r "var_dump(function_exists('imagettftext'));"  # FreeType
```

---

## 安装

```bash
composer require phgors/gocaptcha
```

`composer.json`：

```json
{ "require": { "phgors/gocaptcha": "^1.0" } }
```

---

## 核心概念

本库采用**生成 — 存储 — 校验**三段式无状态设计：

```
┌─────────────┐     generate()      ┌──────────────────┐
│   Builder    │ ──────────────────▶ │  CaptchaData     │
│ (装配配置/素材)│                    │ masterImage/base64│
└─────────────┘                     │ thumbImage /base64│
                                     │ dots / block (答案)│
                                     └────────┬─────────┘
                                              │
                          前端取图像   ────────┼──────── 答案存 Session/Cache
                                              │
┌─────────────┐     validate()       ┌────────▼─────────┐
│  Validator   │ ◀────────────────── │  用户提交的交互    │
│ (静态纯函数) │                     │  点坐标/滑动/角度  │
└─────────────┘                     └──────────────────┘
```

**关键约定**：

1. **生成**：`Builder` 链式装配配置与素材，`build()` 产出 `Captcha`，调用 `generate()` 返回 `CaptchaData`。
2. **图像给前端**：`CaptchaData::toArray()` 只返回图像 base64（`masterImage` / `thumbImage` / `tileImage`），**不含答案**。
3. **答案自己存**：`getDots()` / `getBlock()` 取出答案，由你存入 Session / Cache / Redis / 签名 token。**库不绑定任何存储**。
4. **校验**：用户提交交互数据后，取出已存答案，调用对应 `Validator::validate()`。Validator 是无状态静态方法。

> 图像格式：所有主图为 **JPEG**，缩略图 / 拼图块为 **PNG（含透明通道）**。

---

## 快速开始

最小可用示例（点选验证码）：

```php
require 'vendor/autoload.php';

use Phgors\GoCaptcha\Click\ClickBuilder;
use Phgors\GoCaptcha\Assets\DefaultAssets;
use Phgors\GoCaptcha\Click\ClickValidator;

// 1. 生成
$captcha = ClickBuilder::make()
    ->setBackgrounds(DefaultAssets::backgrounds())
    ->setFonts(DefaultAssets::fonts())
    ->setChars(DefaultAssets::chineseChars())
    ->build();

$data = $captcha->generate();

// 2. 图像给前端，答案存服务端
session_start();
$_SESSION['captcha_dots'] = array_map(fn($d) => $d->toArray(), $data->getDots());
header('Content-Type: application/json; charset=utf-8');
echo json_encode($data->toArray(), JSON_UNESCAPED_SLASHES);

// 3. 校验（下一次请求）
// $userPoints = [['x'=>120,'y'=>80], ...];   前端回传
// $storedDots = $_SESSION['captcha_dots'];
// $ok = ClickValidator::validate($storedDots, $userPoints, 10);
```

> `DefaultAssets` 提供内置字体、背景、字符集与拼图，**无需任何配置即可生成**。

---

## 点选验证码（Click）

用户在主图中按缩略图提示的顺序点击对应字符 / 图形。

### 文本模式（默认）

```php
use Phgors\GoCaptcha\Click\ClickBuilder;
use Phgors\GoCaptcha\Assets\DefaultAssets;
use Phgors\GoCaptcha\Click\ClickValidator;

$captcha = ClickBuilder::make()
    ->setBackgrounds(DefaultAssets::backgrounds())   // 必填：背景图路径数组
    ->setFonts(DefaultAssets::fonts())               // 必填：字体（文本模式）
    ->setChars(DefaultAssets::chineseChars())        // 必填：字符池（数量须 >= rangeLen.Max）
    ->build();

$data = $captcha->generate();

$data->getMasterImage();  // JpegImage（主图，含若干字符）
$data->getThumbImage();   // PngImage（缩略图，提示要点哪些）
$data->getDots();         // Dot[]（答案：目标字符的坐标，顺序即点击顺序）

$payload = $data->toArray();
// ['masterImage' => 'data:image/jpeg;base64,...', 'thumbImage' => 'data:image/png;base64,...']
```

### 图形模式

```php
$captcha = ClickBuilder::make()
    ->setBackgrounds(DefaultAssets::backgrounds())
    ->setShapes([
        'circle' => '/path/to/circle.png',
        'star'   => '/path/to/star.png',
        'square' => '/path/to/square.png',
        // ... 数量须 >= rangeLen.Max
    ])
    ->buildShape();   // 注意：图形模式用 buildShape()，无需字体
```

### 生成结果：`Dot`

| 方法 | 类型 | 说明 |
|------|------|------|
| `getIndex()` | int | 目标点序号（点击顺序） |
| `getX()` | int | 主图坐标 X |
| `getY()` | int | 主图坐标 Y |
| `getSize()` | int | 渲染尺寸（像素） |
| `toArray()` | array | `{index, x, y, size}` |

### 校验

```php
// 批量校验：用户点的所有坐标，顺序无关，每个目标点须被恰好命中
$ok = ClickValidator::validate($dots, $userPoints, $padding);

// 单点校验：判断某一点是否命中某个目标
$ok = ClickValidator::checkPoint($srcX, $srcY, $dot, $padding);
```

| 参数 | 说明 |
|------|------|
| `$dots` | `Dot[]` 或 `array[]`（含 x/y/size）— 服务端存的答案 |
| `$userPoints` | `[['x'=>int,'y'=>int], ...]` — 前端回传的用户点击坐标 |
| `$padding` | 容差像素（如 10），点落在目标 `±padding` 方框内即算命中 |
| 返回 | bool；点数不符直接返回 false |

> `validate` 内部做**无序匹配**：用户不必按顺序点，只要每个目标点都被某个用户点击命中即可。

---

## 滑动验证码（Slide）

用户滑动 / 拖拽拼图块到主图缺口位置。

### 基本模式（沿水平方向滑动）

```php
use Phgors\GoCaptcha\Slide\SlideBuilder;
use Phgors\GoCaptcha\Slide\GraphImage;
use Phgors\GoCaptcha\Assets\DefaultAssets;
use Phgors\GoCaptcha\Slide\SlideValidator;

// 用内置拼图素材构造 GraphImage 列表
$graphs = array_map(function ($dir) {
    return new GraphImage("$dir/overlay.png", "$dir/mask.png", "$dir/shadow.png");
}, DefaultAssets::tileSets());

$captcha = SlideBuilder::make()
    ->setBackgrounds(DefaultAssets::backgrounds())   // 必填
    ->setGraphs($graphs)                              // 必填：拼图素材
    ->build();

$data = $captcha->generate();

$data->getMasterImage();  // JpegImage（主图，含缺口阴影）
$data->getTileImage();    // PngImage（拼图块，带透明通道）
$data->getBlock();        // Block（答案：缺口目标位置）

$payload = $data->toArray();
// ['masterImage' => '...', 'tileImage' => '...']
```

### 拖拽模式（区域自由拖动）

```php
$captcha = SlideBuilder::make()
    ->setBackgrounds(DefaultAssets::backgrounds())
    ->setGraphs($graphs)
    ->buildRegion();   // 拖拽模式：拼图块 Y 坐标也随机
```

### 答案：`Block`

| 方法 | 类型 | 说明 |
|------|------|------|
| `getX()` | int | 目标缺口 X（拼图块应对齐的 X） |
| `getY()` | int | 目标缺口 Y |
| `getWidth()` | int | 拼图块宽 |
| `getHeight()` | int | 拼图块高 |
| `getAngle()` | int | 角度（基本模式恒为 0，预留） |
| `toArray()` | array | `{x, y, width, height, angle}` |

### 校验

```php
$ok = SlideValidator::validate($block, $userX, $userY, $padding);
// 用户滑动的终点 (userX, userY) 与目标 (block.x, block.y) 差值均在 padding 内即通过
```

---

## 旋转验证码（Rotate）

用户旋转圆形缩略图，使其与主图角度对齐。

> **角度约定（与前端兼容）**：主图 master 是被旋转了 `answer` 角度的背景；缩略图 thumb 取自**未旋转**的背景。用户需旋转 thumb 去对齐 master，前端回传用户旋转角，后端校验是否 ≈ `answer`。

```php
use Phgors\GoCaptcha\Rotate\RotateBuilder;
use Phgors\GoCaptcha\Assets\DefaultAssets;
use Phgors\GoCaptcha\Rotate\RotateValidator;

$captcha = RotateBuilder::make()
    ->setBackgrounds(DefaultAssets::backgrounds())   // 必填
    ->build();

$data = $captcha->generate();

$data->getMasterImage();  // JpegImage（旋转后的主图）
$data->getThumbImage();   // PngImage（圆形缩略图，未旋转源裁剪）
$data->getBlock();        // RotateBlock（答案：目标角度）

$angle = $data->getBlock()->getAngle();   // 0~360
```

### 校验（环形角度）

```php
$ok = RotateValidator::validate($angle, $userAngle, $padding);
// 自动处理 0°/360° 邻接：差值 > 180 取 360-差值，最终差值 <= padding 即通过
```

例：`validate(358, 2, 10)` → 实际差 4° → 通过。

---

## 配置参考

每种验证码通过 `with*()` 链构造**不可变** `Options`，再用 `setOptions()` 注入 Builder。`with*()` 返回新实例，不修改原对象。

### ClickOptions

| 方法 | 默认值 | 说明 |
|------|--------|------|
| `withImageSize(Size)` | 300×220 | 主图尺寸 |
| `withThumbSize(Size)` | 150×40 | 缩略图尺寸 |
| `withRangeLen(RangeVal)` | 4~5 | 主图生成字符总数范围 |
| `withRangeVerifyLen(RangeVal)` | 2~4 | 需点击的目标字符数范围 |
| `withRangeSize(RangeVal)` | 26~34 | 字号范围（px） |
| `withRangeColors(string[])` | `['#ffffff','#ffeebb','#aabbcc']` | 字符随机颜色池 |
| `withDisplayShadow(bool)` | false | 是否显示字符阴影 |
| `withShadowColor(string)` | `#000000` | 阴影颜色 |
| `withShadowOffset(int $x,int $y)` | 1,1 | 阴影偏移 |

```php
use Phgors\GoCaptcha\Click\ClickOptions;
use Phgors\GoCaptcha\Base\Size;
use Phgors\GoCaptcha\Base\RangeVal;

$options = (new ClickOptions())
    ->withImageSize(new Size(320, 240))
    ->withRangeVerifyLen(new RangeVal(3, 3))   // 固定点 3 个
    ->withDisplayShadow(true);

$captcha = ClickBuilder::make()
    ->setBackgrounds(DefaultAssets::backgrounds())
    ->setFonts(DefaultAssets::fonts())
    ->setChars(DefaultAssets::chineseChars())
    ->setOptions($options)
    ->build();
```

> 约束：字符池（或图形池）数量必须 **>= `rangeLen.Max`**，否则 `build()` 抛 `GenerationException`。

### SlideOptions

| 方法 | 默认值 | 说明 |
|------|--------|------|
| `withImageSize(Size)` | 300×220 | 主图尺寸 |
| `withRangeGraphSize(RangeVal)` | 50~70 | 拼图块尺寸范围（px，正方形） |
| `withGraphNumber(int)` | 1 | 拼图块个数（当前实现渲染单个） |
| `withEnableGraphVerticalRandom(bool)` | false | 拼图块 Y 方向是否随机 |
| `withRegionMode(bool)` | false | 是否拖拽模式（通常用 `buildRegion()`） |

### RotateOptions

| 方法 | 默认值 | 说明 |
|------|--------|------|
| `withImageSquareSize(int)` | 220 | 主图边长（正方形） |
| `withThumbSquareSize(int)` | 150 | 缩略图边长（正方形） |
| `withRangeAngle(RangeVal)` | 0~360 | 目标角度范围 |
| `withThumbAlpha(float)` | 1.0 | 缩略图不透明度（0.0 全透 ~ 1.0 不透） |

---

## 图像输出

`generate()` 返回的图像为 `JpegImage` / `PngImage` 值对象：

### JpegImage（主图）

| 方法 | 说明 |
|------|------|
| `toBytes(int $quality = 80)` | 原始 JPEG 字节 |
| `toBase64Data(int $quality = 80)` | 纯 base64 字符串（无前缀） |
| `toBase64(int $quality = 80)` | `data:image/jpeg;base64,...` |
| `saveToFile(string $path, int $quality = 80)` | 保存为文件 |

### PngImage（缩略图 / 拼图块）

| 方法 | 说明 |
|------|------|
| `toBytes()` | 原始 PNG 字节（含透明通道） |
| `toBase64Data()` | 纯 base64 字符串 |
| `toBase64()` | `data:image/png;base64,...` |
| `saveToFile(string $path)` | 保存为文件 |

```php
// 直接输出图片到浏览器
header('Content-Type: image/jpeg');
echo $data->getMasterImage()->toBytes();

// 存盘
$data->getThumbImage()->saveToFile(__DIR__ . '/tmp/thumb.png');

// 用 quality 控制 JPEG 体积
$img = $data->getMasterImage()->toBase64(60);  // 更高压缩
```

---

## 素材资源

### 内置素材（开箱即用）

`DefaultAssets` 自动发现包内 `resources/` 目录的素材：

| 方法 | 返回 | 说明 |
|------|------|------|
| `DefaultAssets::backgrounds()` | `string[]` | 内置背景图（.jpg） |
| `DefaultAssets::fonts()` | `Font[]` | 内置字体（.ttf / .otf） |
| `DefaultAssets::chineseChars()` | `string[]` | 默认中文字符池 |
| `DefaultAssets::alnumChars()` | `string[]` | 默认字母数字字符池 |
| `DefaultAssets::tileSets()` | `string[]` | 拼图素材子目录路径数组 |

### 自定义素材（三种方式）

**方式 1：传入路径数组**（最直接）

```php
ClickBuilder::make()
    ->setBackgrounds(['/my/bg1.jpg', '/my/bg2.jpg'])
    ->setFonts([new Font('/my/font.ttf', 24)])
    ->setChars(['甲','乙','丙','丁','戊','己','庚','辛','壬','癸'])
    ->build();
```

**方式 2：自定义 `AssetLoader` 资源目录**

```php
use Phgors\GoCaptcha\Assets\AssetLoader;

// 让 DefaultAssets 指向你自己的资源目录
DefaultAssets::getLoader();   // 默认单例指向包内 resources/
// 你也可以直接 new AssetLoader('/path/to/your/resources') 并自行调用 listFiles()
```

**方式 3：替换包内 `resources/` 文件**（发布时固化默认素材）

### 拼图素材格式规范（Slide）

每个拼图套件是一个目录，含三张**等尺寸** PNG：

```
tiles/my_set/
├── overlay.png   拼图块贴图（彩色，不透明，整张填色即可）
├── mask.png      形状蒙版（形状内 纯白 #FFFFFF，形状外 纯黑 #000000）
└── shadow.png    阴影蒙版（形状内 中灰 ~#787878，形状外 黑）
```

- `mask` 决定拼图块与缺口的**形状**：白色区域=拼图块实体、黑色区域=透明。
- `overlay` 是拼图块的**颜色/纹理**。
- `shadow` 决定主图上缺口的**阴影深浅**。

### 背景图建议

- 点选 / 滑动：横向矩形（默认 300×220 渲染，建议原图 ≥ 600×440）。
- 旋转：会裁剪为正方形，建议用接近正方形的图。

### 字体

- 必须是 TTF / OTF；中文渲染需 CJK 字体。
- 内置 `Noto Sans SC`（SIL OFL-1.1）。生产环境如需更小体积，可用 [fonttools](https://github.com/fonttools/fonttools) 子集化到仅含所需字符（~几十 KB）。

---

## 随机数与可复现性

`Phgors\GoCaptcha\Base\Rng` 是可注入的随机源：

- **默认**（无种子）：用 `random_int()`，密码学级质量，适合生产。
- **种子模式**：`new Rng($seed)`，内部每实例独立 xorshift32，`getInt/pick/shuffle` 全部可复现——**用于测试**。

```php
use Phgors\GoCaptcha\Base\Rng;

$captcha = ClickBuilder::make()
    ->setRng(new Rng(2026))   // 固定种子
    ->setBackgrounds(...)
    ->build();

// 相同种子 → 相同图像、相同答案，便于测试断言
```

> `RangeVal::random()` 是值对象上的便捷方法，使用全局随机，不参与种子化生成流程；生成器统一走 `Rng`。

---

## 异常处理

所有异常实现 `Phgors\GoCaptcha\Exception\CaptchaException`（继承 `\Throwable`）：

| 异常 | 触发场景 |
|------|----------|
| `ResourceException` | 素材缺失、图片解码失败、字体无效 |
| `GenerationException` | 配置非法（如字符数 < `rangeLen.Max`）、放置碰撞超限 |
| `ValidationException` | 校验入参类型/缺失（预留） |

```php
use Phgors\GoCaptcha\Exception\ResourceException;
use Phgors\GoCaptcha\Exception\GenerationException;

try {
    $data = $captcha->generate();
} catch (ResourceException $e) {
    // 素材问题：记日志、提示运维
} catch (GenerationException $e) {
    // 配置问题：调整参数
}
```

---

## 与前端对接

本库产出图像与答案；**返回给前端的 JSON 字段名需与你使用的前端组件约定对齐**。

### 返回示例（适配 go-captcha 前端组件）

go-captcha 的各前端库（Vue/React/Angular/Svelte/Solid/UniApp）期望后端返回图像，并在校验时回传用户交互数据。常见映射方式：

```php
// 生成接口 GET /captcha/click
$data = $captcha->generate();
$_SESSION['click_answer'] = array_map(fn($d) => $d->toArray(), $data->getDots());

return json_encode([
    'code'  => 0,
    'image' => $data->getMasterImage()->toBase64(),   // 主图
    'thumb' => $data->getThumbImage()->toBase64(),    // 缩略图
    // 'captcha_id' => $sessionId,   // 若无 Session，可用签名 token 标识本次验证
]);
```

```php
// 校验接口 POST /captcha/verify
$userPoints = $_POST['points'];   // 前端回传：[[x,y],...] 或 [{x,y},...]
$dots       = $_SESSION['click_answer'];
$ok = ClickValidator::validate($dots, $userPoints, 10);
```

### 三种验证码的前端字段对照

| 类型 | 返回前端图像 | 校验时前端回传 |
|------|--------------|----------------|
| Click | `masterImage` + `thumbImage` | 点击坐标数组 `[{x,y},...]` |
| Slide | `masterImage` + `tileImage` | 滑动终点 `{x, y}` |
| Rotate | `masterImage` + `thumbImage` | 旋转角度 `angle`（度） |

> 提示：go-captcha 前端组件默认会自动管理"主图 / 缩略图"的展示与交互，你只需把后端返回的 base64 图像喂给它，并把它收集到的交互数据回传到校验接口。具体字段名以你所用前端组件版本为准。

---

## 框架集成示例

### 原生 PHP

见 [快速开始](#快速开始)。

### Laravel

```php
// routes/web.php
use Phgors\GoCaptcha\Click\ClickBuilder;
use Phgors\GoCaptcha\Assets\DefaultAssets;
use Phgors\GoCaptcha\Click\ClickValidator;
use Illuminate\Support\Facades\Session;

Route::get('/captcha/click', function () {
    $captcha = ClickBuilder::make()
        ->setBackgrounds(DefaultAssets::backgrounds())
        ->setFonts(DefaultAssets::fonts())
        ->setChars(DefaultAssets::chineseChars())
        ->build();
    $data = $captcha->generate();

    Session::put('captcha_dots', array_map(fn($d) => $d->toArray(), $data->getDots()));

    return response()->json($data->toArray());
});

Route::post('/captcha/verify', function (\Illuminate\Http\Request $req) {
    $ok = ClickValidator::validate(
        Session::get('captcha_dots', []),
        $req->input('points', []),
        10
    );
    return response()->json(['ok' => $ok]);
});
```

### ThinkPHP / 其他框架

逻辑一致：在控制器里 `generate()` → 图像返前端、答案存会话 → 校验时取答案调 `Validator`。Builder 实例可缓存（持有不可变配置，可重复 `generate()`）。

---

## 常见问题

**Q: 图像里中文是方块/豆腐？**
A: GD 未启用 FreeType，或字体不含对应字形。确认 `function_exists('imagettftext')` 为 true，并使用 CJK 字体（内置 Noto Sans SC 已支持）。

**Q: 包体积偏大？**
A: 主要是内置字体。可在 `resources/fonts/` 放子集化后的字体（仅含所用字符）大幅瘦身。

**Q: 如何生成不同尺寸 / 难度？**
A: 用 `Options` 的 `with*()` 方法调整（见 [配置参考](#配置参考)）。

**Q: 多次 `generate()` 会复用配置吗？**
A: 会。`build()` 产出的 `Captcha` 持有不可变配置，可安全重复调用 `generate()`，每次产出新随机结果。

**Q: 答案能不存 Session 吗？**
A: 可以。库是无状态的，你可用 Cache / Redis / JWT 等任何方式持久化 `getDots()/getBlock()` 的结果（推荐存 `toArray()`）。

**Q: 校验容差 `padding` 怎么取？**
A: 点选常用 8~15，滑动常用 3~8，旋转常用 5~10。值越小越严格、越难通过。

---

## API 速查表

### 点选（Click）

```
ClickBuilder::make()
    ->setBackgrounds(string[])      必填
    ->setFonts(Font[])              文本模式必填
    ->setChars(string[])            文本模式必填（>= rangeLen.Max）
    ->setShapes(array<string,string>) 图形模式必填
    ->setOptions(ClickOptions)      可选
    ->setRng(Rng)                   可选
    ->build()                       → ClickCaptcha（文本模式）
    ->buildShape()                  → ClickCaptcha（图形模式）

ClickCaptcha::generate() → ClickCaptchaData
    ->getMasterImage(): JpegImage
    ->getThumbImage():  PngImage
    ->getDots():        Dot[]
    ->toArray():        array  (masterImage, thumbImage)

ClickValidator::validate(Dot[]|array[] $dots, array $userPoints, int $padding): bool
ClickValidator::checkPoint(int $x, int $y, Dot $dot, int $padding): bool
```

### 滑动（Slide）

```
SlideBuilder::make()
    ->setBackgrounds(string[])      必填
    ->setGraphs(GraphImage[])       必填
    ->setOptions(SlideOptions)      可选
    ->setRng(Rng)                   可选
    ->build()                       → SlideCaptcha（基本模式）
    ->buildRegion()                 → SlideCaptcha（拖拽模式）

new GraphImage(string $overlayPath, string $maskPath, string $shadowPath)

SlideCaptcha::generate() → SlideCaptchaData
    ->getMasterImage(): JpegImage
    ->getTileImage():    PngImage
    ->getBlock():        Block
    ->toArray():         array  (masterImage, tileImage)

SlideValidator::validate(Block $block, int $userX, int $userY, int $padding): bool
```

### 旋转（Rotate）

```
RotateBuilder::make()
    ->setBackgrounds(string[])      必填
    ->setOptions(RotateOptions)     可选
    ->setRng(Rng)                   可选
    ->build()                       → RotateCaptcha

RotateCaptcha::generate() → RotateCaptchaData
    ->getMasterImage(): JpegImage
    ->getThumbImage():  PngImage
    ->getBlock():       RotateBlock    ->getAngle(): int
    ->toArray():        array  (masterImage, thumbImage)

RotateValidator::validate(int $angle, int $userAngle, int $padding): bool
```

### 资源（Assets）

```
DefaultAssets::backgrounds():  string[]
DefaultAssets::fonts():        Font[]
DefaultAssets::chineseChars(): string[]
DefaultAssets::alnumChars():   string[]
DefaultAssets::tileSets():     string[]   // 拼图套件目录

new Font(string $path, int $size = 24)
new AssetLoader(?string $resourcesDir = null)
    ->listFiles(string $subDir, string ...$exts): string[]
    ->requireFile(string $subPath): string
```

### 值对象（Base）

```
new Size(int $width, int $height)         ->getWidth()/getHeight()
new Point(int $x, int $y)                 ->getX()/getY()
new RangeVal(int $min, int $max)          ->getMin()/getMax()/random()
Color::fromHex('#rrggbb')                 ->getR()/getG()/getB()/getAlpha()/allocate($gd)
new Rng(?int $seed = null)                ->getInt($min,$max)/range(RangeVal)/pick(array)/shuffle(array)
```

---

## 协议

- 本库源码：**Apache License 2.0**
- 内置字体（Noto Sans SC）：**SIL Open Font License 1.1**，声明见 `resources/fonts/LICENSE-OFL.txt`。依据 OFL 条款，字体版权声明与许可证随字体一同再分发。

## 致谢

基于 [wenlng/go-captcha](https://github.com/wenlng/go-captcha)（Apache-2.0）的设计与算法移植。
