# phgors/gocaptcha

PHP 行为验证码库（点选 / 滑动 / 旋转），[go-captcha](https://github.com/wenlng/go-captcha) 生态的 PHP 后端实现。
基于 GD，开箱即用，纯无状态，框架无关，内置默认字体 / 背景 / 拼图素材。

## 特性

- **点选验证码**：文本点选与图形点选双模式，含主图 + 缩略图
- **滑动验证码**：基本模式与区域拖拽模式，支持任意拼图素材
- **旋转验证码**：圆形缩略图角度对齐
- **纯无状态**：所有校验器为静态纯函数，答案由调用方存 Session / Cache
- **内置素材**：自带字体、背景图、拼图套件，零配置即可生成
- **PHP 7.4+**，仅依赖 `ext-gd`（建议启用 FreeType）

## 安装

```bash
composer require phgors/gocaptcha
```

要求：PHP >= 7.4，`ext-gd`（建议启用 FreeType 以支持中文字符渲染）。

## 点选验证码

```php
use Phgors\GoCaptcha\Click\ClickBuilder;
use Phgors\GoCaptcha\Assets\DefaultAssets;
use Phgors\GoCaptcha\Click\ClickValidator;

$captcha = ClickBuilder::make()
    ->setBackgrounds(DefaultAssets::backgrounds())
    ->setFonts(DefaultAssets::fonts())
    ->setChars(DefaultAssets::chineseChars())
    ->build();

$data = $captcha->generate();

// 返回给前端
$payload = $data->toArray(); // ['masterImage' => ..., 'thumbImage' => ...]
// $data->getDots() 自行存 Session / Cache 作为答案

// 校验（下一次请求，用户点击的点）
$ok = ClickValidator::validate($storedDots, $userPoints, $padding = 10);
```

图形点选模式使用 `buildShape()`，并通过 `setShapes()` 传入图形映射。

## 滑动验证码

```php
use Phgors\GoCaptcha\Slide\SlideBuilder;
use Phgors\GoCaptcha\Slide\GraphImage;
use Phgors\GoCaptcha\Assets\DefaultAssets;
use Phgors\GoCaptcha\Slide\SlideValidator;

$graphs = array_map(function ($dir) {
    return new GraphImage("$dir/overlay.png", "$dir/mask.png", "$dir/shadow.png");
}, DefaultAssets::tileSets());

$captcha = SlideBuilder::make()
    ->setBackgrounds(DefaultAssets::backgrounds())
    ->setGraphs($graphs)
    ->build(); // 或 ->buildRegion() 区域拖拽模式

$data = $captcha->generate();
$payload = $data->toArray();     // masterImage + tileImage
$block = $data->getBlock();      // 存 Session / Cache

$ok = SlideValidator::validate($block, $userX, $userY, $padding = 5);
```

## 旋转验证码

```php
use Phgors\GoCaptcha\Rotate\RotateBuilder;
use Phgors\GoCaptcha\Assets\DefaultAssets;
use Phgors\GoCaptcha\Rotate\RotateValidator;

$captcha = RotateBuilder::make()
    ->setBackgrounds(DefaultAssets::backgrounds())
    ->build();

$data = $captcha->generate();
$payload = $data->toArray();
$angle = $data->getBlock()->getAngle(); // 存 Session / Cache

$ok = RotateValidator::validate($angle, $userAngle, $padding = 8);
```

## 配置

每种验证码通过 `with*()` 方法构造不可变的 Options 对象，再用 `setOptions()` 注入 Builder。
下面给出常用项，完整选项见 `src/` 下各 `*Options.php` 的默认值。

```php
use Phgors\GoCaptcha\Click\ClickOptions;
use Phgors\GoCaptcha\Base\Size;
use Phgors\GoCaptcha\Base\RangeVal;

$options = (new ClickOptions())
    ->withImageSize(new Size(300, 220))        // 主图尺寸
    ->withThumbSize(new Size(150, 40))         // 缩略图尺寸
    ->withRangeLen(new RangeVal(4, 5))         // 生成字符个数范围
    ->withRangeVerifyLen(new RangeVal(2, 4))   // 需点击个数范围
    ->withRangeSize(new RangeVal(26, 34))      // 字号范围
    ->withDisplayShadow(true);                 // 文字阴影

$captcha = ClickBuilder::make()
    ->setBackgrounds(DefaultAssets::backgrounds())
    ->setFonts(DefaultAssets::fonts())
    ->setChars(DefaultAssets::chineseChars())
    ->setOptions($options)
    ->build();
```

`SlideOptions` 提供 `withImageSize`、`withRangeGraphSize`、`withGraphNumber`、
`withEnableGraphVerticalRandom`；`RotateOptions` 提供 `withImageSquareSize`、
`withThumbSquareSize`、`withRangeAngle`、`withThumbAlpha`。

## 自定义素材

有两种方式：

1. **替换默认目录中的文件**：把素材放入 `resources/fonts`（`.ttf` / `.otf` 自动加载）、
   `resources/backgrounds`（`.jpg`）、`resources/tiles/<set>/`（`overlay.png` / `mask.png` / `shadow.png`），
   `DefaultAssets` 会自动发现。

2. **传入绝对路径**：直接把素材路径数组传入对应 Builder 的 setter，
   或构造自定义 `AssetLoader` 指向自己的资源目录。

## 协议

- 本库源码：**Apache-2.0**
- 内置字体（Noto Sans SC）：**SIL Open Font License 1.1**，声明见 `resources/fonts/LICENSE-OFL.txt`

依据 OFL 条款，字体的版权声明与许可证全文随字体一同再分发。
