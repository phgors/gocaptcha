# phgors/gocaptcha 实现计划

> **执行者须知：** 使用 superpowers:subagent-driven-development（推荐）或 superpowers:executing-plans 按任务逐个实现。步骤使用复选框（`- [ ]`）语法跟踪。

**目标：** 用 PHP（GD 扩展）全量重写 go-captcha 的三种验证码（点选/滑动/旋转）生成与校验，内置默认素材，发布到 packagist。

**架构：** 分层 + 共享 Base 层，镜像 Go 主库结构。`Base/` 封装 GD 与值对象，三种验证码各自独立依赖 Base；`ImageData/` 解耦图像输出；`Assets/` 提供素材加载与内置默认。

**技术栈：** PHP 7.4+ / ext-gd / PHPUnit 9.6 / PSR-4

**设计文档：** `docs/superpowers/specs/2026-06-18-php-gocaptcha-design.md`

**语法约束：** 仅用 PHP 7.4 兼容语法（typed properties、arrow fn 可用；禁用 named args、union types、match、enum、readonly、first-class callable、intersection types）。

**Git 提交规范：** commit message 用中文，格式 `<type>: <中文描述>`，不加 Co-Authored-By。

**环境：** 本地 PHP 8.2.12（GD 含 FreeType/JPEG/PNG 已启用）。目标兼容 7.4+。

---

## 文件结构总览

| 路径 | 职责 |
|------|------|
| `composer.json` | 包定义、PSR-4 自动加载 |
| `phpunit.xml.dist` | PHPUnit 配置 |
| `.gitignore` | 忽略 vendor/、.caches/ 等 |
| `LICENSE` | Apache-2.0 |
| `src/Base/Size.php` `Point.php` `RangeVal.php` `Color.php` | 不可变值对象 |
| `src/Base/Rng.php` | 可注入种子的随机源 |
| `src/Base/Canvas.php` | GD 画布封装（所有 image* 调用集中处） |
| `src/Base/ImageEncoder.php` | 图像编码为 bytes/base64 的工具 |
| `src/Base/Distortion.php` | 扭曲/噪点圆/干扰线算法 |
| `src/Exception/*.php` | 异常层级 |
| `src/ImageData/JpegImage.php` `PngImage.php` | 图像值对象（输出） |
| `src/Assets/Font.php` `AssetLoader.php` `DefaultAssets.php` | 素材加载 |
| `src/Click/*.php` | 点选验证码 |
| `src/Slide/*.php` | 滑动验证码（含 MaskProcessor） |
| `src/Rotate/*.php` | 旋转验证码 |
| `resources/*` | 内置字体/背景/拼图/字符集 |
| `tests/*` | PHPUnit 测试 |

---

# 阶段 0：脚手架

### Task 0.1：项目脚手架与自动加载

**Files:**
- Create: `composer.json`
- Create: `.gitignore`
- Create: `LICENSE`
- Create: `phpunit.xml.dist`
- Create: `src/.gitkeep`, `tests/.gitkeep`

- [ ] **Step 1：创建 `composer.json`**

```json
{
    "name": "phgors/gocaptcha",
    "description": "PHP 行为验证码库（点选/滑动/旋转），go-captcha 生态 PHP 后端实现",
    "type": "library",
    "license": "Apache-2.0",
    "keywords": ["captcha", "go-captcha", "click", "slide", "rotate", "gd"],
    "authors": [
        {"name": "phgors"}
    ],
    "require": {
        "php": ">=7.4",
        "ext-gd": "*"
    },
    "require-dev": {
        "phpunit/phpunit": "^9.6"
    },
    "autoload": {
        "psr-4": {
            "Phgors\\GoCaptcha\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Phgors\\GoCaptcha\\Tests\\": "tests/"
        }
    }
}
```

- [ ] **Step 2：创建 `.gitignore`**

```
/vendor/
/.caches/
/.idea/
/.vscode/
composer.lock
.phpunit.result.cache
```

- [ ] **Step 3：创建 `LICENSE`（Apache-2.0 全文）**

从 https://www.apache.org/licenses/LICENSE-2.0.txt 复制全文，年份填 2026，版权人 `phgors`。

- [ ] **Step 4：创建 `phpunit.xml.dist`**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true"
         cacheResultFile=".phpunit.result.cache">
    <testsuites>
        <testsuite name="all">
            <directory>tests</directory>
        </testsuite>
    </testsuites>
    <coverage>
        <include>
            <directory>src</directory>
        </include>
    </coverage>
</phpunit>
```

- [ ] **Step 5：安装依赖并初始化 git**

```bash
git init
git remote add origin git@github.com:phgors/gocaptcha.git
composer update
```
预期：生成 `vendor/`，无报错。

- [ ] **Step 6：提交**

```bash
git add .
git commit -m "chore: 初始化项目脚手架与依赖"
```

---

# 阶段 1：Base 层（值对象 + 基础设施）

### Task 1.1：异常层级

**Files:**
- Create: `src/Exception/CaptchaException.php`
- Create: `src/Exception/ResourceException.php`
- Create: `src/Exception/GenerationException.php`
- Create: `src/Exception/ValidationException.php`

- [ ] **Step 1：创建接口与三个异常类**

```php
<?php
// src/Exception/CaptchaException.php
namespace Phgors\GoCaptcha\Exception;

interface CaptchaException extends \Throwable
{
}
```

```php
<?php
// src/Exception/ResourceException.php
namespace Phgors\GoCaptcha\Exception;

class ResourceException extends \RuntimeException implements CaptchaException
{
}
```

```php
<?php
// src/Exception/GenerationException.php
namespace Phgors\GoCaptcha\Exception;

class GenerationException extends \RuntimeException implements CaptchaException
{
}
```

```php
<?php
// src/Exception/ValidationException.php
namespace Phgors\GoCaptcha\Exception;

class ValidationException extends \RuntimeException implements CaptchaException
{
}
```

- [ ] **Step 2：提交**

```bash
git add src/Exception
git commit -m "feat: 添加异常层级结构"
```

---

### Task 1.2：Size / Point / RangeVal 值对象（TDD）

**Files:**
- Create: `src/Base/Size.php`
- Create: `src/Base/Point.php`
- Create: `src/Base/RangeVal.php`
- Test: `tests/Base/ValueObjectTest.php`

- [ ] **Step 1：写失败测试**

```php
<?php
// tests/Base/ValueObjectTest.php
namespace Phgors\GoCaptcha\Tests\Base;

use Phgors\GoCaptcha\Base\Size;
use Phgors\GoCaptcha\Base\Point;
use Phgors\GoCaptcha\Base\RangeVal;
use PHPUnit\Framework\TestCase;

class ValueObjectTest extends TestCase
{
    public function test_size_holds_dimensions(): void
    {
        $s = new Size(300, 220);
        self::assertSame(300, $s->getWidth());
        self::assertSame(220, $s->getHeight());
    }

    public function test_size_rejects_non_positive(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Size(0, 10);
    }

    public function test_point_holds_coordinates(): void
    {
        $p = new Point(5, 7);
        self::assertSame(5, $p->getX());
        self::assertSame(7, $p->getY());
    }

    public function test_range_val_holds_min_max(): void
    {
        $r = new RangeVal(2, 4);
        self::assertSame(2, $r->getMin());
        self::assertSame(4, $r->getMax());
    }

    public function test_range_val_rejects_min_greater_than_max(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RangeVal(5, 3);
    }

    public function test_range_val_random_within(): void
    {
        $r = new RangeVal(10, 20);
        for ($i = 0; $i < 50; $i++) {
            $v = $r->random();
            self::assertGreaterThanOrEqual(10, $v);
            self::assertLessThanOrEqual(20, $v);
        }
    }
}
```

- [ ] **Step 2：运行测试验证失败**

```bash
vendor/bin/phpunit tests/Base/ValueObjectTest.php
```
预期：FAIL（类未找到）。

- [ ] **Step 3：实现三个值对象**

```php
<?php
// src/Base/Size.php
namespace Phgors\GoCaptcha\Base;

final class Size
{
    private int $width;
    private int $height;

    public function __construct(int $width, int $height)
    {
        if ($width <= 0 || $height <= 0) {
            throw new \InvalidArgumentException('尺寸必须为正数');
        }
        $this->width = $width;
        $this->height = $height;
    }

    public function getWidth(): int
    {
        return $this->width;
    }

    public function getHeight(): int
    {
        return $this->height;
    }
}
```

```php
<?php
// src/Base/Point.php
namespace Phgors\GoCaptcha\Base;

final class Point
{
    private int $x;
    private int $y;

    public function __construct(int $x, int $y)
    {
        $this->x = $x;
        $this->y = $y;
    }

    public function getX(): int
    {
        return $this->x;
    }

    public function getY(): int
    {
        return $this->y;
    }
}
```

```php
<?php
// src/Base/RangeVal.php
namespace Phgors\GoCaptcha\Base;

final class RangeVal
{
    private int $min;
    private int $max;

    public function __construct(int $min, int $max)
    {
        if ($min > $max) {
            throw new \InvalidArgumentException('min 不能大于 max');
        }
        $this->min = $min;
        $this->max = $max;
    }

    public function getMin(): int
    {
        return $this->min;
    }

    public function getMax(): int
    {
        return $this->max;
    }

    public function random(): int
    {
        return mt_rand($this->min, $this->max);
    }
}
```

- [ ] **Step 4：运行测试验证通过**

```bash
vendor/bin/phpunit tests/Base/ValueObjectTest.php
```
预期：PASS（6 个测试）。

- [ ] **Step 5：提交**

```bash
git add src/Base tests/Base
git commit -m "feat: 添加 Size/Point/RangeVal 值对象"
```

---

### Task 1.3：Color 值对象（TDD）

**Files:**
- Create: `src/Base/Color.php`
- Test: `tests/Base/ColorTest.php`

- [ ] **Step 1：写失败测试**

```php
<?php
// tests/Base/ColorTest.php
namespace Phgors\GoCaptcha\Tests\Base;

use Phgors\GoCaptcha\Base\Color;
use PHPUnit\Framework\TestCase;

class ColorTest extends TestCase
{
    public function test_from_hex_6_digits(): void
    {
        $c = Color::fromHex('#FF8800');
        self::assertSame(255, $c->getR());
        self::assertSame(136, $c->getG());
        self::assertSame(0, $c->getB());
    }

    public function test_from_hex_without_hash(): void
    {
        $c = Color::fromHex('00ff00');
        self::assertSame(0, $c->getR());
        self::assertSame(255, $c->getG());
        self::assertSame(0, $c->getB());
    }

    public function test_from_hex_3_digits_expanded(): void
    {
        $c = Color::fromHex('#f80');
        self::assertSame(255, $c->getR());
        self::assertSame(136, $c->getG());
        self::assertSame(0, $c->getB());
    }

    public function test_default_alpha_is_zero(): void
    {
        $c = Color::fromHex('#000000');
        self::assertSame(0, $c->getAlpha());
    }

    public function test_invalid_hex_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Color::fromHex('xyz');
    }

    public function test_rgb_out_of_range_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Color(300, 0, 0);
    }
}
```

- [ ] **Step 2：运行验证失败**

```bash
vendor/bin/phpunit tests/Base/ColorTest.php
```

- [ ] **Step 3：实现 Color**

```php
<?php
// src/Base/Color.php
namespace Phgors\GoCaptcha\Base;

final class Color
{
    private int $r;
    private int $g;
    private int $b;
    private int $alpha;

    public function __construct(int $r, int $g, int $b, int $alpha = 0)
    {
        if ($r < 0 || $r > 255 || $g < 0 || $g > 255 || $b < 0 || $b > 255) {
            throw new \InvalidArgumentException('RGB 分量必须在 0-255 之间');
        }
        if ($alpha < 0 || $alpha > 127) {
            throw new \InvalidArgumentException('alpha 必须在 0-127 之间');
        }
        $this->r = $r;
        $this->g = $g;
        $this->b = $b;
        $this->alpha = $alpha;
    }

    public static function fromHex(string $hex): self
    {
        $hex = ltrim($hex, '#');
        if (!ctype_xdigit($hex) || (strlen($hex) !== 3 && strlen($hex) !== 6)) {
            throw new \InvalidArgumentException('非法十六进制颜色：' . $hex);
        }
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        return new self(
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2))
        );
    }

    public function getR(): int { return $this->r; }
    public function getG(): int { return $this->g; }
    public function getB(): int { return $this->b; }
    public function getAlpha(): int { return $this->alpha; }

    public function allocate($image): int
    {
        return imagecolorallocatealpha($image, $this->r, $this->g, $this->b, $this->alpha);
    }
}
```

- [ ] **Step 4：运行验证通过**

```bash
vendor/bin/phpunit tests/Base/ColorTest.php
```

- [ ] **Step 5：提交**

```bash
git add src/Base/Color.php tests/Base/ColorTest.php
git commit -m "feat: 添加 Color 值对象与 hex 解析"
```

---

### Task 1.4：Rng 可注入随机源（TDD）

**Files:**
- Create: `src/Base/Rng.php`
- Test: `tests/Base/RngTest.php`

- [ ] **Step 1：写失败测试**

```php
<?php
// tests/Base/RngTest.php
namespace Phgors\GoCaptcha\Tests\Base;

use Phgors\GoCaptcha\Base\Rng;
use Phgors\GoCaptcha\Base\RangeVal;
use PHPUnit\Framework\TestCase;

class RngTest extends TestCase
{
    public function test_get_int_within_range(): void
    {
        $rng = new Rng();
        for ($i = 0; $i < 100; $i++) {
            $v = $rng->getInt(5, 9);
            self::assertGreaterThanOrEqual(5, $v);
            self::assertLessThanOrEqual(9, $v);
        }
    }

    public function test_seeded_reproducible(): void
    {
        $a = new Rng(123);
        $b = new Rng(123);
        $seqA = [];
        $seqB = [];
        for ($i = 0; $i < 10; $i++) {
            $seqA[] = $a->getInt(0, 1000);
            $seqB[] = $b->getInt(0, 1000);
        }
        self::assertSame($seqA, $seqB);
    }

    public function test_range_uses_range_val(): void
    {
        $rng = new Rng(42);
        $v = $rng->range(new RangeVal(10, 20));
        self::assertGreaterThanOrEqual(10, $v);
        self::assertLessThanOrEqual(20, $v);
    }

    public function test_pick_returns_element_of_array(): void
    {
        $rng = new Rng(1);
        $items = ['a', 'b', 'c'];
        self::assertContains($rng->pick($items), $items);
    }

    public function test_shuffle_preserves_elements(): void
    {
        $rng = new Rng(7);
        $items = ['a', 'b', 'c', 'd'];
        $shuffled = $rng->shuffle($items);
        sort($shuffled);
        self::assertSame(['a', 'b', 'c', 'd'], $shuffled);
    }
}
```

- [ ] **Step 2：运行验证失败**

- [ ] **Step 3：实现 Rng**

```php
<?php
// src/Base/Rng.php
namespace Phgors\GoCaptcha\Base;

final class Rng
{
    private ?int $seed;

    public function __construct(?int $seed = null)
    {
        $this->seed = $seed;
        if ($seed !== null) {
            mt_srand($seed);
        }
    }

    public function getInt(int $min, int $max): int
    {
        return mt_rand($min, $max);
    }

    public function range(RangeVal $range): int
    {
        return $this->getInt($range->getMin(), $range->getMax());
    }

    public function pick(array $items)
    {
        if ($items === []) {
            throw new \InvalidArgumentException('不能从空数组选取');
        }
        $index = $this->getInt(0, count($items) - 1);
        return array_values($items)[$index];
    }

    public function shuffle(array $items): array
    {
        shuffle($items);
        return $items;
    }
}
```

- [ ] **Step 4：运行验证通过**

```bash
vendor/bin/phpunit tests/Base/RngTest.php
```

- [ ] **Step 5：提交**

```bash
git add src/Base/Rng.php tests/Base/RngTest.php
git commit -m "feat: 添加可注入种子的 Rng 随机源"
```

---

### Task 1.5：Canvas GD 画布封装

**Files:**
- Create: `src/Base/Canvas.php`
- Test: `tests/Base/CanvasTest.php`

> 说明：Canvas 依赖 ext-gd，测试为集成测试。

- [ ] **Step 1：写测试**

```php
<?php
// tests/Base/CanvasTest.php
namespace Phgors\GoCaptcha\Tests\Base;

use Phgors\GoCaptcha\Base\Canvas;
use Phgors\GoCaptcha\Base\Color;
use Phgors\GoCaptcha\Base\Size;
use Phgors\GoCaptcha\Exception\ResourceException;
use PHPUnit\Framework\TestCase;

class CanvasTest extends TestCase
{
    private $tmpBg;

    protected function tearDown(): void
    {
        if ($this->tmpBg && file_exists($this->tmpBg)) {
            unlink($this->tmpBg);
        }
        parent::tearDown();
    }

    public function test_create_with_size(): void
    {
        $c = new Canvas(300, 220);
        self::assertSame(300, $c->getWidth());
        self::assertSame(220, $c->getHeight());
        $c->destroy();
    }

    public function test_from_path_jpeg(): void
    {
        $this->tmpBg = __DIR__ . '/../fixtures/tmp_bg.jpg';
        $src = new Canvas(60, 40);
        imagefilledrectangle($src->getResource(), 0, 0, 60, 40, imagecolorallocate($src->getResource(), 255, 0, 0));
        imagejpeg($src->getResource(), $this->tmpBg);
        $src->destroy();

        $c = Canvas::fromPath($this->tmpBg);
        self::assertSame(60, $c->getWidth());
        $c->destroy();
    }

    public function test_from_missing_path_throws(): void
    {
        $this->expectException(ResourceException::class);
        Canvas::fromPath(__DIR__ . '/nonexistent.jpg');
    }

    public function test_fill_and_pixel(): void
    {
        $c = new Canvas(10, 10);
        $c->fill(Color::fromHex('#000000'));
        $c->setPixel(5, 5, $c->allocateColor(Color::fromHex('#ffffff')));
        self::assertSame([255, 255, 255], $c->getRgbAt(5, 5));
        $c->destroy();
    }
}
```

- [ ] **Step 2：创建 fixtures 目录占位**

```bash
mkdir -p tests/fixtures
```

- [ ] **Step 3：运行验证失败**

- [ ] **Step 4：实现 Canvas**

```php
<?php
// src/Base/Canvas.php
namespace Phgors\GoCaptcha\Base;

use Phgors\GoCaptcha\Exception\ResourceException;

final class Canvas
{
    /** @var resource|\GdImage */
    private $resource;
    private int $width;
    private int $height;

    public function __construct(int $width, int $height)
    {
        if ($width <= 0 || $height <= 0) {
            throw new \InvalidArgumentException('画布尺寸必须为正数');
        }
        $res = @imagecreatetruecolor($width, $height);
        if ($res === false) {
            throw new ResourceException('画布创建失败');
        }
        $this->resource = $res;
        $this->width = $width;
        $this->height = $height;
        imagealphablending($res, true);
        imagesavealpha($res, true);
        $transparent = imagecolorallocatealpha($res, 0, 0, 0, 127);
        imagefill($res, 0, 0, $transparent);
    }

    public static function fromPath(string $path): self
    {
        if (!file_exists($path)) {
            throw new ResourceException('图片不存在：' . $path);
        }
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($ext === 'jpg' || $ext === 'jpeg') {
            $res = @imagecreatefromjpeg($path);
        } elseif ($ext === 'png') {
            $res = @imagecreatefrompng($path);
        } elseif ($ext === 'gif') {
            $res = @imagecreatefromgif($path);
        } else {
            throw new ResourceException('不支持的图片格式：' . $ext);
        }
        if ($res === false) {
            throw new ResourceException('图片解码失败：' . $path);
        }
        imagealphablending($res, true);
        imagesavealpha($res, true);
        $c = new self(imagesx($res), imagesy($res));
        imagecopy($c->resource, $res, 0, 0, 0, 0, imagesx($res), imagesy($res));
        imagedestroy($res);
        return $c;
    }

    /** @return resource|\GdImage */
    public function getResource()
    {
        return $this->resource;
    }

    /**
     * 取出底层 GD 资源并放弃所有权（析构时不再销毁）。
     * 用于把资源移交给 JpegImage/PngImage 等值对象，避免双重释放。
     * @return resource|\GdImage|null
     */
    public function releaseResource()
    {
        $res = $this->resource;
        $this->resource = null;
        return $res;
    }

    public function getWidth(): int { return $this->width; }
    public function getHeight(): int { return $this->height; }

    public function allocateColor(Color $color): int
    {
        return imagecolorallocatealpha($this->resource, $color->getR(), $color->getG(), $color->getB(), $color->getAlpha());
    }

    public function fill(Color $color): void
    {
        imagefill($this->resource, 0, 0, $this->allocateColor($color));
    }

    public function setPixel(int $x, int $y, int $color): void
    {
        imagesetpixel($this->resource, $x, $y, $color);
    }

    /**
     * @return array{0:int,1:int,2:int}|null [r,g,b]
     */
    public function getRgbAt(int $x, int $y): ?array
    {
        $rgb = imagecolorat($this->resource, $x, $y);
        if ($rgb === false) {
            return null;
        }
        return [(int)(($rgb >> 16) & 0xFF), (int)(($rgb >> 8) & 0xFF), (int)($rgb & 0xFF)];
    }

    public function getAlphaAt(int $x, int $y): int
    {
        $rgb = imagecolorat($this->resource, $x, $y);
        if ($rgb === false) {
            return 127;
        }
        return (int)(($rgb >> 24) & 0x7F);
    }

    public function copy(self $src, int $dstX, int $dstY, int $srcX, int $srcY, int $w, int $h): void
    {
        imagecopy($this->resource, $src->getResource(), $dstX, $dstY, $srcX, $srcY, $w, $h);
    }

    public function copyResampled(self $src, int $dstX, int $dstY, int $srcX, int $srcY, int $dstW, int $dstH, int $srcW, int $srcH): void
    {
        imagecopyresampled($this->resource, $src->getResource(), $dstX, $dstY, $srcX, $srcY, $dstW, $dstH, $srcW, $srcH);
    }

    public function mergeAlpha(self $src, int $dstX, int $dstY, int $w, int $h): void
    {
        imagecopy($this->resource, $src->getResource(), $dstX, $dstY, 0, 0, $w, $h);
    }

    public function ttfText(float $size, float $angle, int $x, int $y, int $color, string $fontPath, string $text): void
    {
        imagettftext($this->resource, $size, $angle, $x, $y, $color, $fontPath, $text);
    }

    public function rotate(float $angle, int $backgroundColor): self
    {
        $rotated = imagerotate($this->resource, $angle, $backgroundColor);
        if ($rotated === false) {
            throw new ResourceException('图像旋转失败');
        }
        $c = new self(imagesx($rotated), imagesy($rotated));
        imagecopy($c->resource, $rotated, 0, 0, 0, 0, imagesx($rotated), imagesy($rotated));
        imagedestroy($rotated);
        return $c;
    }

    public function crop(int $x, int $y, int $w, int $h): self
    {
        $cropped = imagecrop($this->resource, ['x' => $x, 'y' => $y, 'width' => $w, 'height' => $h]);
        if ($cropped === false) {
            throw new ResourceException('图像裁剪失败');
        }
        $c = new self($w, $h);
        imagecopy($c->resource, $cropped, 0, 0, 0, 0, $w, $h);
        imagedestroy($cropped);
        return $c;
    }

    public function destroy(): void
    {
        if ($this->resource !== null) {
            imagedestroy($this->resource);
            $this->resource = null;
        }
    }

    public function __destruct()
    {
        $this->destroy();
    }
}
```

- [ ] **Step 5：运行验证通过**

```bash
vendor/bin/phpunit tests/Base/CanvasTest.php
```

- [ ] **Step 6：提交**

```bash
git add src/Base/Canvas.php tests/Base/CanvasTest.php
git commit -m "feat: 添加 Canvas GD 画布封装"
```

---

### Task 1.6：ImageData（JpegImage / PngImage）+ ImageEncoder（TDD）

**Files:**
- Create: `src/ImageData/JpegImage.php`
- Create: `src/ImageData/PngImage.php`
- Test: `tests/ImageData/ImageDataTest.php`

- [ ] **Step 1：写测试**

```php
<?php
// tests/ImageData/ImageDataTest.php
namespace Phgors\GoCaptcha\Tests\ImageData;

use Phgors\GoCaptcha\ImageData\JpegImage;
use Phgors\GoCaptcha\ImageData\PngImage;
use PHPUnit\Framework\TestCase;

class ImageDataTest extends TestCase
{
    private function makeGd()
    {
        $res = imagecreatetruecolor(20, 10);
        imagecolorallocate($res, 255, 0, 0);
        return $res;
    }

    public function test_jpeg_to_base64_has_data_uri_prefix(): void
    {
        $img = new JpegImage($this->makeGd());
        self::assertStringStartsWith('data:image/jpeg;base64,', $img->toBase64());
        $img->destroy();
    }

    public function test_jpeg_to_base64_data_is_raw_base64(): void
    {
        $img = new JpegImage($this->makeGd());
        self::assertSame($img->toBase64Data(), base64_encode($img->toBytes()));
        $img->destroy();
    }

    public function test_png_to_base64_has_data_uri_prefix(): void
    {
        $img = new PngImage($this->makeGd());
        self::assertStringStartsWith('data:image/png;base64,', $img->toBase64());
        $img->destroy();
    }

    public function test_jpeg_bytes_start_with_magic(): void
    {
        $img = new JpegImage($this->makeGd());
        $bytes = $img->toBytes();
        self::assertSame("\xFF\xD8", substr($bytes, 0, 2));
        $img->destroy();
    }

    public function test_png_bytes_start_with_magic(): void
    {
        $img = new PngImage($this->makeGd());
        $bytes = $img->toBytes();
        self::assertSame("\x89PNG", substr($bytes, 0, 4));
        $img->destroy();
    }

    public function test_save_to_file(): void
    {
        $path = __DIR__ . '/../fixtures/out_test.png';
        $img = new PngImage($this->makeGd());
        $img->saveToFile($path);
        self::assertFileExists($path);
        unlink($path);
        $img->destroy();
    }
}
```

- [ ] **Step 2：运行验证失败**

- [ ] **Step 3：实现 JpegImage**

```php
<?php
// src/ImageData/JpegImage.php
namespace Phgors\GoCaptcha\ImageData;

final class JpegImage
{
    /** @var resource|\GdImage|null */
    private $resource;
    private bool $owned;

    /**
     * @param resource|\GdImage $resource
     * @param bool $owned 是否由本对象负责销毁（默认 true）
     */
    public function __construct($resource, bool $owned = true)
    {
        $this->resource = $resource;
        $this->owned = $owned;
    }

    public function toBytes(int $quality = 80): string
    {
        ob_start();
        imagejpeg($this->resource, null, $quality);
        return (string)ob_get_clean();
    }

    public function toBase64Data(int $quality = 80): string
    {
        return base64_encode($this->toBytes($quality));
    }

    public function toBase64(int $quality = 80): string
    {
        return 'data:image/jpeg;base64,' . $this->toBase64Data($quality);
    }

    public function saveToFile(string $path, int $quality = 80): void
    {
        if (imagejpeg($this->resource, $path, $quality) === false) {
            throw new \RuntimeException('JPEG 保存失败：' . $path);
        }
    }

    public function destroy(): void
    {
        if ($this->owned && $this->resource !== null) {
            imagedestroy($this->resource);
            $this->resource = null;
        }
    }

    public function __destruct()
    {
        $this->destroy();
    }
}
```

- [ ] **Step 4：实现 PngImage**

```php
<?php
// src/ImageData/PngImage.php
namespace Phgors\GoCaptcha\ImageData;

final class PngImage
{
    /** @var resource|\GdImage|null */
    private $resource;
    private bool $owned;

    /**
     * @param resource|\GdImage $resource
     */
    public function __construct($resource, bool $owned = true)
    {
        $this->resource = $resource;
        $this->owned = $owned;
    }

    public function toBytes(): string
    {
        ob_start();
        imagepng($this->resource);
        return (string)ob_get_clean();
    }

    public function toBase64Data(): string
    {
        return base64_encode($this->toBytes());
    }

    public function toBase64(): string
    {
        return 'data:image/png;base64,' . $this->toBase64Data();
    }

    public function saveToFile(string $path): void
    {
        if (imagepng($this->resource, $path) === false) {
            throw new \RuntimeException('PNG 保存失败：' . $path);
        }
    }

    public function destroy(): void
    {
        if ($this->owned && $this->resource !== null) {
            imagedestroy($this->resource);
            $this->resource = null;
        }
    }

    public function __destruct()
    {
        $this->destroy();
    }
}
```

- [ ] **Step 5：运行验证通过**

```bash
vendor/bin/phpunit tests/ImageData/ImageDataTest.php
```

- [ ] **Step 6：提交**

```bash
git add src/ImageData tests/ImageData
git commit -m "feat: 添加 JpegImage/PngImage 图像值对象"
```

---

### Task 1.7：Distortion 扭曲/干扰算法（TDD）

**Files:**
- Create: `src/Base/Distortion.php`
- Test: `tests/Base/DistortionTest.php`

> Distortion 提供缩略图背景的视觉变形：正弦扭曲、小圆点噪点、贝塞尔/直线干扰线。纯绘制操作依赖 GD，集成测试验证"执行后画布可正常输出"。

- [ ] **Step 1：写测试**

```php
<?php
// tests/Base/DistortionTest.php
namespace Phgors\GoCaptcha\Tests\Base;

use Phgors\GoCaptcha\Base\Canvas;
use Phgors\GoCaptcha\Base\Color;
use Phgors\GoCaptcha\Base\Distortion;
use Phgors\GoCaptcha\Base\Rng;
use PHPUnit\Framework\TestCase;

class DistortionTest extends TestCase
{
    public function test_draw_circles_runs_without_error(): void
    {
        $c = new Canvas(150, 40);
        $d = new Distortion(new Rng(1));
        $d->drawCircles($c, 30, Color::fromHex('#cccccc'));
        self::assertIsArray($c->getRgbAt(10, 10));
        $c->destroy();
    }

    public function test_draw_lines_runs_without_error(): void
    {
        $c = new Canvas(150, 40);
        $d = new Distortion(new Rng(2));
        $d->drawSlimLines($c, 5, Color::fromHex('#999999'));
        $c->destroy();
    }

    public function test_draw_dots_runs_without_error(): void
    {
        $c = new Canvas(150, 40);
        $d = new Distortion(new Rng(3));
        $d->drawDots($c, 100, Color::fromHex('#666666'));
        $c->destroy();
    }
}
```

- [ ] **Step 2：运行验证失败**

- [ ] **Step 3：实现 Distortion**

```php
<?php
// src/Base/Distortion.php
namespace Phgors\GoCaptcha\Base;

final class Distortion
{
    private Rng $rng;

    public function __construct(Rng $rng)
    {
        $this->rng = $rng;
    }

    public function drawCircles(Canvas $canvas, int $num, Color $color, int $maxRadius = 4): void
    {
        $c = $canvas->allocateColor($color);
        $w = $canvas->getWidth();
        $h = $canvas->getHeight();
        for ($i = 0; $i < $num; $i++) {
            $r = $this->rng->getInt(1, max(1, $maxRadius));
            $x = $this->rng->getInt(0, $w - 1);
            $y = $this->rng->getInt(0, $h - 1);
            imagefilledellipse($canvas->getResource(), $x, $y, $r * 2, $r * 2, $c);
        }
    }

    public function drawDots(Canvas $canvas, int $num, Color $color): void
    {
        $c = $canvas->allocateColor($color);
        $w = $canvas->getWidth();
        $h = $canvas->getHeight();
        for ($i = 0; $i < $num; $i++) {
            $canvas->setPixel($this->rng->getInt(0, $w - 1), $this->rng->getInt(0, $h - 1), $c);
        }
    }

    public function drawSlimLines(Canvas $canvas, int $num, Color $color): void
    {
        $c = $canvas->allocateColor($color);
        $w = $canvas->getWidth();
        $h = $canvas->getHeight();
        for ($i = 0; $i < $num; $i++) {
            $x1 = $this->rng->getInt(0, $w - 1);
            $y1 = $this->rng->getInt(0, $h - 1);
            $x2 = $this->rng->getInt(0, $w - 1);
            $y2 = $this->rng->getInt(0, $h - 1);
            imageline($canvas->getResource(), $x1, $y1, $x2, $y2, $c);
        }
    }
}
```

- [ ] **Step 4：运行验证通过**

```bash
vendor/bin/phpunit tests/Base/DistortionTest.php
```

- [ ] **Step 5：提交**

```bash
git add src/Base/Distortion.php tests/Base/DistortionTest.php
git commit -m "feat: 添加 Distortion 扭曲/干扰绘制"
```

---

# 阶段 2：素材加载（Assets）

### Task 2.1：Font / AssetLoader / DefaultAssets（TDD）

**Files:**
- Create: `src/Assets/Font.php`
- Create: `src/Assets/AssetLoader.php`
- Create: `src/Assets/DefaultAssets.php`
- Create: `resources/chars.php`
- Test: `tests/Assets/AssetsTest.php`

- [ ] **Step 1：创建字符集资源文件**

```php
<?php
// resources/chars.php
return [
    'chinese' => ['丹','王','李','天','地','人','山','水','日','月','星','辰','云','雨','风','雪'],
    'alnum'   => array_merge(range('A','Z'), range('a','z'), range('0','9')),
];
```

- [ ] **Step 2：写测试**

```php
<?php
// tests/Assets/AssetsTest.php
namespace Phgors\GoCaptcha\Tests\Assets;

use Phgors\GoCaptcha\Assets\Font;
use Phgors\GoCaptcha\Assets\DefaultAssets;
use PHPUnit\Framework\TestCase;

class AssetsTest extends TestCase
{
    public function test_font_holds_path_and_size(): void
    {
        $f = new Font('/path/font.ttf', 24);
        self::assertSame('/path/font.ttf', $f->getPath());
        self::assertSame(24, $f->getSize());
    }

    public function test_default_chars_returns_arrays(): void
    {
        $chars = DefaultAssets::chineseChars();
        self::assertIsArray($chars);
        self::assertNotEmpty($chars);
        self::assertNotEmpty(DefaultAssets::alnumChars());
    }
}
```

- [ ] **Step 3：运行验证失败**

- [ ] **Step 4：实现 Font**

```php
<?php
// src/Assets/Font.php
namespace Phgors\GoCaptcha\Assets;

final class Font
{
    private string $path;
    private int $size;

    public function __construct(string $path, int $size = 24)
    {
        $this->path = $path;
        $this->size = $size;
    }

    public function getPath(): string { return $this->path; }
    public function getSize(): int { return $this->size; }
}
```

- [ ] **Step 5：实现 AssetLoader**

```php
<?php
// src/Assets/AssetLoader.php
namespace Phgors\GoCaptcha\Assets;

use Phgors\GoCaptcha\Exception\ResourceException;

final class AssetLoader
{
    private string $resourcesDir;

    public function __construct(?string $resourcesDir = null)
    {
        $this->resourcesDir = $resourcesDir ?? dirname(__DIR__, 2) . '/resources';
    }

    public function getResourcesDir(): string
    {
        return $this->resourcesDir;
    }

    /**
     * @return string[]
     */
    public function listFiles(string $subDir, string $extension): array
    {
        $dir = $this->resourcesDir . '/' . trim($subDir, '/');
        if (!is_dir($dir)) {
            return [];
        }
        $result = [];
        foreach (glob($dir . '/*.' . $extension) as $file) {
            $result[] = $file;
        }
        return $result;
    }

    public function requireFile(string $subPath): string
    {
        $full = $this->resourcesDir . '/' . ltrim($subPath, '/');
        if (!file_exists($full)) {
            throw new ResourceException('资源文件不存在：' . $subPath);
        }
        return $full;
    }
}
```

- [ ] **Step 6：实现 DefaultAssets**

```php
<?php
// src/Assets/DefaultAssets.php
namespace Phgors\GoCaptcha\Assets;

final class DefaultAssets
{
    private static ?AssetLoader $loader = null;

    public static function getLoader(): AssetLoader
    {
        if (self::$loader === null) {
            self::$loader = new AssetLoader();
        }
        return self::$loader;
    }

    /**
     * @return string[]
     */
    public static function backgrounds(): array
    {
        return self::getLoader()->listFiles('backgrounds', 'jpg');
    }

    /**
     * @return Font[]
     */
    public static function fonts(): array
    {
        $files = self::getLoader()->listFiles('fonts', 'ttf');
        $fonts = [];
        foreach ($files as $f) {
            $fonts[] = new Font($f);
        }
        return $fonts;
    }

    /**
     * @return string[]
     */
    public static function chineseChars(): array
    {
        $chars = require self::getLoader()->requireFile('chars.php');
        return $chars['chinese'] ?? [];
    }

    /**
     * @return string[]
     */
    public static function alnumChars(): array
    {
        $chars = require self::getLoader()->requireFile('chars.php');
        return $chars['alnum'] ?? [];
    }

    /**
     * @return string[] 每个元素是 tiles 目录下的子目录路径（含 overlay/mask/shadow）
     */
    public static function tileSets(): array
    {
        $dir = self::getLoader()->getResourcesDir() . '/tiles';
        if (!is_dir($dir)) {
            return [];
        }
        $sets = [];
        foreach (glob($dir . '/*', GLOB_ONLYDIR) as $d) {
            $sets[] = $d;
        }
        return $sets;
    }
}
```

- [ ] **Step 7：运行验证通过**

```bash
vendor/bin/phpunit tests/Assets/AssetsTest.php
```

- [ ] **Step 8：提交**

```bash
git add src/Assets tests/Assets resources/chars.php
git commit -m "feat: 添加 Font/AssetLoader/DefaultAssets 素材加载"
```

---

# 阶段 3：点选验证码（Click）

### Task 3.1：Dot + ClickOptions + ClickResourceBag（TDD）

**Files:**
- Create: `src/Click/Dot.php`
- Create: `src/Click/ClickOptions.php`
- Create: `src/Click/ClickResourceBag.php`
- Test: `tests/Click/DotTest.php`

- [ ] **Step 1：写测试**

```php
<?php
// tests/Click/DotTest.php
namespace Phgors\GoCaptcha\Tests\Click;

use Phgors\GoCaptcha\Click\Dot;
use Phgors\GoCaptcha\Click\ClickOptions;
use Phgors\GoCaptcha\Base\Size;
use Phgors\GoCaptcha\Base\RangeVal;
use PHPUnit\Framework\TestCase;

class DotTest extends TestCase
{
    public function test_dot_holds_values(): void
    {
        $d = new Dot(0, 100, 120, 32);
        self::assertSame(0, $d->getIndex());
        self::assertSame(100, $d->getX());
        self::assertSame(120, $d->getY());
        self::assertSame(32, $d->getSize());
    }

    public function test_click_options_defaults(): void
    {
        $o = new ClickOptions();
        self::assertSame(300, $o->getImageSize()->getWidth());
        self::assertSame(220, $o->getImageSize()->getHeight());
        self::assertSame(150, $o->getThumbSize()->getWidth());
        self::assertSame(40, $o->getThumbSize()->getHeight());
        self::assertFalse($o->isDisplayShadow());
    }

    public function test_click_options_with_chain(): void
    {
        $o = (new ClickOptions())
            ->withImageSize(new Size(400, 300))
            ->withRangeLen(new RangeVal(3, 6))
            ->withDisplayShadow(true);
        self::assertSame(400, $o->getImageSize()->getWidth());
        self::assertSame(6, $o->getRangeLen()->getMax());
        self::assertTrue($o->isDisplayShadow());
    }
}
```

- [ ] **Step 2：运行验证失败**

- [ ] **Step 3：实现 Dot**

```php
<?php
// src/Click/Dot.php
namespace Phgors\GoCaptcha\Click;

final class Dot
{
    private int $index;
    private int $x;
    private int $y;
    private int $size;

    public function __construct(int $index, int $x, int $y, int $size)
    {
        $this->index = $index;
        $this->x = $x;
        $this->y = $y;
        $this->size = $size;
    }

    public function getIndex(): int { return $this->index; }
    public function getX(): int { return $this->x; }
    public function getY(): int { return $this->y; }
    public function getSize(): int { return $this->size; }

    public function toArray(): array
    {
        return ['index' => $this->index, 'x' => $this->x, 'y' => $this->y, 'size' => $this->size];
    }
}
```

- [ ] **Step 4：实现 ClickOptions（不可变，with* 返回新实例）**

```php
<?php
// src/Click/ClickOptions.php
namespace Phgors\GoCaptcha\Click;

use Phgors\GoCaptcha\Base\Size;
use Phgors\GoCaptcha\Base\RangeVal;

final class ClickOptions
{
    private Size $imageSize;
    private Size $thumbSize;
    private RangeVal $rangeLen;
    private RangeVal $rangeVerifyLen;
    private RangeVal $rangeSize;
    /** @var string[] */
    private array $rangeColors;
    private bool $displayShadow;
    private string $shadowColor;
    private int $shadowOffsetX;
    private int $shadowOffsetY;

    public function __construct()
    {
        $this->imageSize = new Size(300, 220);
        $this->thumbSize = new Size(150, 40);
        $this->rangeLen = new RangeVal(4, 5);
        $this->rangeVerifyLen = new RangeVal(2, 4);
        $this->rangeSize = new RangeVal(26, 34);
        $this->rangeColors = ['#ffffff', '#ffeebb', '#aabbcc'];
        $this->displayShadow = false;
        $this->shadowColor = '#000000';
        $this->shadowOffsetX = 1;
        $this->shadowOffsetY = 1;
    }

    public function getImageSize(): Size { return $this->imageSize; }
    public function getThumbSize(): Size { return $this->thumbSize; }
    public function getRangeLen(): RangeVal { return $this->rangeLen; }
    public function getRangeVerifyLen(): RangeVal { return $this->rangeVerifyLen; }
    public function getRangeSize(): RangeVal { return $this->rangeSize; }
    /** @return string[] */
    public function getRangeColors(): array { return $this->rangeColors; }
    public function isDisplayShadow(): bool { return $this->displayShadow; }
    public function getShadowColor(): string { return $this->shadowColor; }
    public function getShadowOffsetX(): int { return $this->shadowOffsetX; }
    public function getShadowOffsetY(): int { return $this->shadowOffsetY; }

    public function withImageSize(Size $s): self { $c = clone $this; $c->imageSize = $s; return $c; }
    public function withThumbSize(Size $s): self { $c = clone $this; $c->thumbSize = $s; return $c; }
    public function withRangeLen(RangeVal $r): self { $c = clone $this; $c->rangeLen = $r; return $c; }
    public function withRangeVerifyLen(RangeVal $r): self { $c = clone $this; $c->rangeVerifyLen = $r; return $c; }
    public function withRangeSize(RangeVal $r): self { $c = clone $this; $c->rangeSize = $r; return $c; }
    /** @param string[] $colors */
    public function withRangeColors(array $colors): self { $c = clone $this; $c->rangeColors = $colors; return $c; }
    public function withDisplayShadow(bool $v): self { $c = clone $this; $c->displayShadow = $v; return $c; }
    public function withShadowColor(string $hex): self { $c = clone $this; $c->shadowColor = $hex; return $c; }
    public function withShadowOffset(int $x, int $y): self { $c = clone $this; $c->shadowOffsetX = $x; $c->shadowOffsetY = $y; return $c; }
}
```

- [ ] **Step 5：实现 ClickResourceBag**

```php
<?php
// src/Click/ClickResourceBag.php
namespace Phgors\GoCaptcha\Click;

use Phgors\GoCaptcha\Assets\Font;

final class ClickResourceBag
{
    /** @var string[] */
    private array $chars = [];
    /** @var Font[] */
    private array $fonts = [];
    /** @var string[] 背景图绝对路径 */
    private array $backgrounds = [];
    /** @var array<string,string> shape 名 => 图片路径 */
    private array $shapes = [];

    /** @return string[] */
    public function getChars(): array { return $this->chars; }
    /** @param string[] $chars */
    public function setChars(array $chars): void { $this->chars = array_values($chars); }

    /** @return Font[] */
    public function getFonts(): array { return $this->fonts; }
    /** @param Font[] $fonts */
    public function setFonts(array $fonts): void { $this->fonts = array_values($fonts); }

    /** @return string[] */
    public function getBackgrounds(): array { return $this->backgrounds; }
    /** @param string[] $backgrounds */
    public function setBackgrounds(array $backgrounds): void { $this->backgrounds = array_values($backgrounds); }

    /** @return array<string,string> */
    public function getShapes(): array { return $this->shapes; }
    /** @param array<string,string> $shapes */
    public function setShapes(array $shapes): void { $this->shapes = $shapes; }
}
```

- [ ] **Step 6：运行验证通过**

```bash
vendor/bin/phpunit tests/Click/DotTest.php
```

- [ ] **Step 7：提交**

```bash
git add src/Click tests/Click
git commit -m "feat: 添加点选验证码 Dot/Options/ResourceBag"
```

---

### Task 3.2：ClickBuilder + ClickCaptchaData（TDD）

**Files:**
- Create: `src/Click/ClickBuilder.php`
- Create: `src/Click/ClickCaptchaData.php`
- Test: `tests/Click/ClickBuilderTest.php`

- [ ] **Step 1：写测试**

```php
<?php
// tests/Click/ClickBuilderTest.php
namespace Phgors\GoCaptcha\Tests\Click;

use Phgors\GoCaptcha\Click\ClickBuilder;
use Phgors\GoCaptcha\Click\ClickOptions;
use Phgors\GoCaptcha\Assets\DefaultAssets;
use PHPUnit\Framework\TestCase;

class ClickBuilderTest extends TestCase
{
    public function test_builder_make_returns_captcha(): void
    {
        $captcha = ClickBuilder::make()
            ->setBackgrounds(DefaultAssets::backgrounds())
            ->setFonts(DefaultAssets::fonts())
            ->setChars(DefaultAssets::chineseChars())
            ->build();
        self::assertInstanceOf(\Phgors\GoCaptcha\Click\ClickCaptcha::class, $captcha);
    }

    public function test_builder_make_without_backgrounds_throws(): void
    {
        $this->expectException(\Phgors\GoCaptcha\Exception\ResourceException::class);
        ClickBuilder::make()
            ->setFonts(DefaultAssets::fonts())
            ->setChars(DefaultAssets::chineseChars())
            ->build();
    }

    public function test_builder_requires_chars_count_above_range_max(): void
    {
        $this->expectException(\Phgors\GoCaptcha\Exception\GenerationException::class);
        ClickBuilder::make()
            ->setBackgrounds(DefaultAssets::backgrounds())
            ->setFonts(DefaultAssets::fonts())
            ->setChars(['丹', '王'])  // 少于 rangeLen.Max(5)
            ->build();
    }
}
```

> 说明：本测试要求 `resources/fonts/` 已存在至少一个字体、`resources/backgrounds/` 至少一张图。若尚未放入内置素材，可先放入最小占位资源（见阶段 5 Task 5.1），或临时用 mock 路径。**建议先执行阶段 5 Task 5.1 放入素材再回来跑本测试**。

- [ ] **Step 2：实现 ClickBuilder**

```php
<?php
// src/Click/ClickBuilder.php
namespace Phgors\GoCaptcha\Click;

use Phgors\GoCaptcha\Assets\Font;
use Phgors\GoCaptcha\Base\Rng;
use Phgors\GoCaptcha\Exception\GenerationException;
use Phgors\GoCaptcha\Exception\ResourceException;

final class ClickBuilder
{
    private ClickOptions $options;
    private ClickResourceBag $resources;
    private ?Rng $rng = null;
    private bool $shapeMode = false;

    public function __construct()
    {
        $this->options = new ClickOptions();
        $this->resources = new ClickResourceBag();
    }

    public static function make(): self
    {
        return new self();
    }

    public function setOptions(ClickOptions $options): self
    {
        $this->options = $options;
        return $this;
    }

    /** @param string[] $chars */
    public function setChars(array $chars): self
    {
        $this->resources->setChars($chars);
        return $this;
    }

    /** @param Font[] $fonts */
    public function setFonts(array $fonts): self
    {
        $this->resources->setFonts($fonts);
        return $this;
    }

    /** @param string[] $backgrounds */
    public function setBackgrounds(array $backgrounds): self
    {
        $this->resources->setBackgrounds($backgrounds);
        return $this;
    }

    /** @param array<string,string> $shapes */
    public function setShapes(array $shapes): self
    {
        $this->resources->setShapes($shapes);
        return $this;
    }

    public function setRng(Rng $rng): self
    {
        $this->rng = $rng;
        return $this;
    }

    public function build(): ClickCaptcha
    {
        $this->validate();
        return new ClickCaptcha(
            $this->options,
            $this->resources,
            $this->rng ?? new Rng(),
            $this->shapeMode
        );
    }

    public function buildShape(): ClickCaptcha
    {
        $this->shapeMode = true;
        return $this->build();
    }

    private function validate(): void
    {
        if ($this->resources->getBackgrounds() === []) {
            throw new ResourceException('点选验证码必须提供背景图');
        }
        if (!$this->shapeMode && $this->resources->getFonts() === []) {
            throw new ResourceException('文本点选必须提供字体');
        }
        $need = $this->options->getRangeLen()->getMax();
        if ($this->shapeMode) {
            if (count($this->resources->getShapes()) < $need) {
                throw new GenerationException('图形素材数量必须大于 rangeLen.Max=' . $need);
            }
        } else {
            if (count($this->resources->getChars()) < $need) {
                throw new GenerationException('字符集数量必须大于 rangeLen.Max=' . $need);
            }
        }
    }
}
```

- [ ] **Step 3：实现 ClickCaptchaData（占位类，generate 在下个 Task 实现）**

```php
<?php
// src/Click/ClickCaptchaData.php
namespace Phgors\GoCaptcha\Click;

use Phgors\GoCaptcha\ImageData\JpegImage;
use Phgors\GoCaptcha\ImageData\PngImage;

final class ClickCaptchaData
{
    /** @var Dot[] */
    private array $dots;
    private JpegImage $masterImage;
    private PngImage $thumbImage;

    /**
     * @param Dot[] $dots
     */
    public function __construct(array $dots, JpegImage $masterImage, PngImage $thumbImage)
    {
        $this->dots = $dots;
        $this->masterImage = $masterImage;
        $this->thumbImage = $thumbImage;
    }

    /** @return Dot[] */
    public function getDots(): array { return $this->dots; }

    public function getMasterImage(): JpegImage { return $this->masterImage; }
    public function getThumbImage(): PngImage { return $this->thumbImage; }

    /**
     * 仅暴露前端需要的字段（图像 base64），不暴露答案 dots。
     */
    public function toArray(): array
    {
        return [
            'masterImage' => $this->masterImage->toBase64(),
            'thumbImage'  => $this->thumbImage->toBase64(),
        ];
    }
}
```

- [ ] **Step 4：创建 ClickCaptcha 占位（下个 Task 实现 generate）**

```php
<?php
// src/Click/ClickCaptcha.php
namespace Phgors\GoCaptcha\Click;

use Phgors\GoCaptcha\Base\Rng;

final class ClickCaptcha
{
    private ClickOptions $options;
    private ClickResourceBag $resources;
    private Rng $rng;
    private bool $shapeMode;

    public function __construct(ClickOptions $options, ClickResourceBag $resources, Rng $rng, bool $shapeMode)
    {
        $this->options = $options;
        $this->resources = $resources;
        $this->rng = $rng;
        $this->shapeMode = $shapeMode;
    }

    public function generate(): ClickCaptchaData
    {
        return (new ClickGenerator($this->options, $this->resources, $this->rng, $this->shapeMode))->generate();
    }
}
```

- [ ] **Step 5：（此 Task 不跑生成测试，留到 3.3）。编译检查 autoload**

```bash
composer dump-autoload
php -r "require 'vendor/autoload.php';"
```
预期：无输出（无致命错误）。

- [ ] **Step 6：提交**

```bash
git add src/Click tests/Click
git commit -m "feat: 添加 ClickBuilder 与 ClickCaptcha 入口"
```

---

### Task 3.3：ClickGenerator 生成核心（TDD，需内置素材）

> 前置：已完成阶段 5 Task 5.1（放入内置字体/背景），否则本测试无法跑。

**Files:**
- Create: `src/Click/ClickGenerator.php`
- Test: `tests/Click/ClickGenerateTest.php`

- [ ] **Step 1：写测试**

```php
<?php
// tests/Click/ClickGenerateTest.php
namespace Phgors\GoCaptcha\Tests\Click;

use Phgors\GoCaptcha\Click\ClickBuilder;
use Phgors\GoCaptcha\Click\ClickCaptchaData;
use Phgors\GoCaptcha\Assets\DefaultAssets;
use PHPUnit\Framework\TestCase;

class ClickGenerateTest extends TestCase
{
    public function test_generate_returns_data_with_images_and_dots(): void
    {
        $captcha = ClickBuilder::make()
            ->setBackgrounds(DefaultAssets::backgrounds())
            ->setFonts(DefaultAssets::fonts())
            ->setChars(DefaultAssets::chineseChars())
            ->build();

        $data = $captcha->generate();

        self::assertInstanceOf(ClickCaptchaData::class, $data);
        self::assertNotEmpty($data->getDots());
        self::assertStringStartsWith('data:image/jpeg;base64,', $data->getMasterImage()->toBase64());
        self::assertStringStartsWith('data:image/png;base64,', $data->getThumbImage()->toBase64());

        foreach ($data->getDots() as $dot) {
            self::assertGreaterThan(0, $dot->getX());
            self::assertGreaterThan(0, $dot->getY());
        }
    }

    public function test_generate_deterministic_with_seed(): void
    {
        $build = function (int $seed) {
            return ClickBuilder::make()
                ->setBackgrounds(DefaultAssets::backgrounds())
                ->setFonts(DefaultAssets::fonts())
                ->setChars(DefaultAssets::chineseChars())
                ->setRng(new \Phgors\GoCaptcha\Base\Rng($seed))
                ->build()
                ->generate();
        };
        $a = $build(999);
        $b = $build(999);
        self::assertSame($a->getDots()[0]->getX(), $b->getDots()[0]->getX());
    }
}
```

- [ ] **Step 2：实现 ClickGenerator**

```php
<?php
// src/Click/ClickGenerator.php
namespace Phgors\GoCaptcha\Click;

use Phgors\GoCaptcha\Assets\Font;
use Phgors\GoCaptcha\Base\Canvas;
use Phgors\GoCaptcha\Base\Color;
use Phgors\GoCaptcha\Base\Distortion;
use Phgors\GoCaptcha\Base\Rng;
use Phgors\GoCaptcha\Exception\GenerationException;
use Phgors\GoCaptcha\Exception\ResourceException;
use Phgors\GoCaptcha\ImageData\JpegImage;
use Phgors\GoCaptcha\ImageData\PngImage;

final class ClickGenerator
{
    private ClickOptions $options;
    private ClickResourceBag $resources;
    private Rng $rng;
    private bool $shapeMode;

    public function __construct(ClickOptions $options, ClickResourceBag $resources, Rng $rng, bool $shapeMode)
    {
        $this->options = $options;
        $this->resources = $resources;
        $this->rng = $rng;
        $this->shapeMode = $shapeMode;
    }

    public function generate(): ClickCaptchaData
    {
        $opts = $this->options;
        $imageSize = $opts->getImageSize();
        $thumbSize = $opts->getThumbSize();

        $total = $this->rng->range($opts->getRangeLen());
        $verifyLen = min($this->rng->range($opts->getRangeVerifyLen()), $total);

        $master = $this->createMasterCanvas($imageSize->getWidth(), $imageSize->getHeight());

        $pool = $this->shapeMode ? array_keys($this->resources->getShapes()) : $this->resources->getChars();
        $chosen = $this->pickUnique($pool, $total);

        $placed = $this->placeItems($master, $chosen, $imageSize);

        $verifyDots = [];
        for ($i = 0; $i < $verifyLen; $i++) {
            $placedItem = $placed[$i];
            $verifyDots[] = new Dot($i, $placedItem['x'], $placedItem['y'], $placedItem['size']);
        }

        $thumb = $this->createThumbCanvas($verifyDots, $chosen, $thumbSize);

        $jpeg = new JpegImage($master->releaseResource());
        $png = new PngImage($thumb->releaseResource());

        return new ClickCaptchaData($verifyDots, $jpeg, $png);
    }

    private function createMasterCanvas(int $w, int $h): Canvas
    {
        $bgs = $this->resources->getBackgrounds();
        $bgPath = $this->rng->pick($bgs);
        $bg = Canvas::fromPath($bgPath);
        $canvas = new Canvas($w, $h);
        $canvas->copyResampled($bg, 0, 0, 0, 0, $w, $h, $bg->getWidth(), $bg->getHeight());
        $bg->destroy();
        return $canvas;
    }

    /**
     * @param array $chosen 已选定的项
     * @return array<int, array{x:int,y:int,size:int,text:string}>
     */
    private function placeItems(Canvas $master, array $chosen, $imageSize): array
    {
        $opts = $this->options;
        $w = $imageSize->getWidth();
        $h = $imageSize->getHeight();
        $placed = [];
        $maxAttempts = 200;
        foreach ($chosen as $text) {
            $size = $this->rng->range($opts->getRangeSize());
            $angle = $this->rng->getInt(-30, 30);
            $colorHex = $this->rng->pick($opts->getRangeColors());
            $color = Color::fromHex($colorHex);

            $attempts = 0;
            do {
                $x = $this->rng->getInt($size + 4, max($size + 5, $w - $size - 4));
                $y = $this->rng->getInt($size + 4, max($size + 5, $h - 4));
                $ok = $this->isPositionFree($x, $y, $size, $placed);
                $attempts++;
            } while (!$ok && $attempts < $maxAttempts);

            if (!$ok) {
                throw new GenerationException('字符放置碰撞超限，请增大画布或减少内容数');
            }

            if ($opts->isDisplayShadow()) {
                $shadow = Color::fromHex($opts->getShadowColor());
                $this->renderItem($master, $text, $size, $angle, $x + $opts->getShadowOffsetX(), $y + $opts->getShadowOffsetY(), $shadow);
            }
            $this->renderItem($master, $text, $size, $angle, $x, $y, $color);
            $placed[] = ['x' => $x, 'y' => $y, 'size' => $size, 'text' => $text];
        }
        return $placed;
    }

    private function isPositionFree(int $x, int $y, int $size, array $placed): bool
    {
        $minDist = (int)($size * 1.4);
        foreach ($placed as $p) {
            $dx = $x - $p['x'];
            $dy = $y - $p['y'];
            if (sqrt($dx * $dx + $dy * $dy) < $minDist) {
                return false;
            }
        }
        return true;
    }

    private function renderItem(Canvas $master, string $text, int $size, int $angle, int $x, int $y, Color $color): void
    {
        if ($this->shapeMode) {
            $shapes = $this->resources->getShapes();
            $path = $shapes[$text] ?? null;
            if ($path === null) {
                throw new ResourceException('图形素材缺失：' . $text);
            }
            $img = Canvas::fromPath($path);
            $dest = new Canvas($size, $size);
            $dest->copyResampled($img, 0, 0, 0, 0, $size, $size, $img->getWidth(), $img->getHeight());
            $master->mergeAlpha($dest, $x - (int)($size / 2), $y - (int)($size / 2), $size, $size);
            $img->destroy();
            $dest->destroy();
        } else {
            $font = $this->rng->pick($this->resources->getFonts());
            $colorIdx = $master->allocateColor($color);
            $master->ttfText((float)$size, (float)$angle, $x, $y, $colorIdx, $font->getPath(), $text);
        }
    }

    /**
     * @param Dot[] $dots
     */
    private function createThumbCanvas(array $dots, array $chosen, $thumbSize): Canvas
    {
        $opts = $this->options;
        $w = $thumbSize->getWidth();
        $h = $thumbSize->getHeight();
        $canvas = new Canvas($w, $h);
        $canvas->fill(Color::fromHex('#ffffff'));

        $distortion = new Distortion($this->rng);
        $distortion->drawSlimLines($canvas, 3, Color::fromHex('#eeeeee'));
        $distortion->drawCircles($canvas, 20, Color::fromHex('#dddddd'), 3);

        $count = count($dots);
        $slotW = (int)($w / max(1, $count));
        foreach ($dots as $i => $dot) {
            $text = $chosen[$i];
            $colorIdx = $canvas->allocateColor(Color::fromHex('#333333'));
            $font = $this->rng->pick($this->resources->getFonts());
            $size = (int)($h * 0.7);
            $tx = (int)($slotW * $i + $slotW / 2);
            $ty = (int)($h * 0.75);
            $canvas->ttfText((float)$size, 0, $tx, $ty, $colorIdx, $font->getPath(), (string)$text);
        }
        return $canvas;
    }

    private function pickUnique(array $pool, int $n): array
    {
        $pool = array_values($pool);
        $keys = $this->rng->shuffle(range(0, count($pool) - 1));
        $result = [];
        for ($i = 0; $i < $n; $i++) {
            $result[] = $pool[$keys[$i]];
        }
        return $result;
    }
}
```

- [ ] **Step 3：运行验证通过**

```bash
vendor/bin/phpunit tests/Click/ClickGenerateTest.php
```
预期：2 个测试 PASS。

- [ ] **Step 4：提交**

```bash
git add src/Click/ClickGenerator.php tests/Click/ClickGenerateTest.php
git commit -m "feat: 实现点选验证码生成核心"
```

---

### Task 3.4：ClickValidator（TDD）

**Files:**
- Create: `src/Click/ClickValidator.php`
- Test: `tests/Click/ClickValidatorTest.php`

- [ ] **Step 1：写测试**

```php
<?php
// tests/Click/ClickValidatorTest.php
namespace Phgors\GoCaptcha\Tests\Click;

use Phgors\GoCaptcha\Click\ClickValidator;
use Phgors\GoCaptcha\Click\Dot;
use PHPUnit\Framework\TestCase;

class ClickValidatorTest extends TestCase
{
    public function test_check_point_within_padding(): void
    {
        $dot = new Dot(0, 100, 100, 30);
        self::assertTrue(ClickValidator::checkPoint(108, 105, $dot, 10));
    }

    public function test_check_point_outside_padding(): void
    {
        $dot = new Dot(0, 100, 100, 30);
        self::assertFalse(ClickValidator::checkPoint(120, 100, $dot, 10));
    }

    public function test_validate_all_matched_in_any_order(): void
    {
        $dots = [new Dot(0, 50, 50, 30), new Dot(1, 200, 150, 30)];
        $points = [['x' => 201, 'y' => 149], ['x' => 49, 'y' => 51]];
        self::assertTrue(ClickValidator::validate($dots, $points, 10));
    }

    public function test_validate_fails_when_one_dot_not_matched(): void
    {
        $dots = [new Dot(0, 50, 50, 30), new Dot(1, 200, 150, 30)];
        $points = [['x' => 49, 'y' => 51], ['x' => 400, 'y' => 400]];
        self::assertFalse(ClickValidator::validate($dots, $points, 10));
    }

    public function test_validate_count_mismatch_fails(): void
    {
        $dots = [new Dot(0, 50, 50, 30)];
        self::assertFalse(ClickValidator::validate($dots, [], 10));
    }
}
```

- [ ] **Step 2：运行验证失败**

- [ ] **Step 3：实现 ClickValidator**

```php
<?php
// src/Click/ClickValidator.php
namespace Phgors\GoCaptcha\Click;

final class ClickValidator
{
    public static function checkPoint(int $srcX, int $srcY, Dot $dot, int $padding): bool
    {
        $half = (int)($dot->getSize() / 2);
        return $srcX >= $dot->getX() - $half - $padding
            && $srcX <= $dot->getX() + $half + $padding
            && $srcY >= $dot->getY() - $half - $padding
            && $srcY <= $dot->getY() + $half + $padding;
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
```

- [ ] **Step 4：运行验证通过**

```bash
vendor/bin/phpunit tests/Click/ClickValidatorTest.php
```

- [ ] **Step 5：提交**

```bash
git add src/Click/ClickValidator.php tests/Click/ClickValidatorTest.php
git commit -m "feat: 实现点选验证码校验器"
```

---

# 阶段 4：滑动验证码（Slide）

### Task 4.1：GraphImage / Block / SlideOptions / SlideCaptchaData（TDD）

**Files:**
- Create: `src/Slide/GraphImage.php`
- Create: `src/Slide/Block.php`
- Create: `src/Slide/SlideOptions.php`
- Create: `src/Slide/SlideCaptchaData.php`
- Test: `tests/Slide/SlideModelTest.php`

- [ ] **Step 1：写测试**

```php
<?php
// tests/Slide/SlideModelTest.php
namespace Phgors\GoCaptcha\Tests\Slide;

use Phgors\GoCaptcha\Slide\Block;
use Phgors\GoCaptcha\Slide\GraphImage;
use Phgors\GoCaptcha\Slide\SlideOptions;
use PHPUnit\Framework\TestCase;

class SlideModelTest extends TestCase
{
    public function test_block_holds_values(): void
    {
        $b = new Block(120, 80, 60, 60, 0);
        self::assertSame(120, $b->getX());
        self::assertSame(80, $b->getY());
        self::assertSame(60, $b->getWidth());
        self::assertSame(0, $b->getAngle());
    }

    public function test_graph_image_holds_paths(): void
    {
        $g = new GraphImage('/p/overlay.png', '/p/mask.png', '/p/shadow.png');
        self::assertSame('/p/overlay.png', $g->getOverlayPath());
        self::assertSame('/p/mask.png', $g->getMaskPath());
        self::assertSame('/p/shadow.png', $g->getShadowPath());
    }

    public function test_slide_options_defaults(): void
    {
        $o = new SlideOptions();
        self::assertSame(300, $o->getImageSize()->getWidth());
    }
}
```

- [ ] **Step 2：实现四个类**

```php
<?php
// src/Slide/Block.php
namespace Phgors\GoCaptcha\Slide;

final class Block
{
    private int $x;
    private int $y;
    private int $width;
    private int $height;
    private int $angle;

    public function __construct(int $x, int $y, int $width, int $height, int $angle)
    {
        $this->x = $x;
        $this->y = $y;
        $this->width = $width;
        $this->height = $height;
        $this->angle = $angle;
    }

    public function getX(): int { return $this->x; }
    public function getY(): int { return $this->y; }
    public function getWidth(): int { return $this->width; }
    public function getHeight(): int { return $this->height; }
    public function getAngle(): int { return $this->angle; }

    public function toArray(): array
    {
        return ['x' => $this->x, 'y' => $this->y, 'width' => $this->width, 'height' => $this->height, 'angle' => $this->angle];
    }
}
```

```php
<?php
// src/Slide/GraphImage.php
namespace Phgors\GoCaptcha\Slide;

final class GraphImage
{
    private string $overlayPath;
    private string $maskPath;
    private string $shadowPath;

    public function __construct(string $overlayPath, string $maskPath, string $shadowPath)
    {
        $this->overlayPath = $overlayPath;
        $this->maskPath = $maskPath;
        $this->shadowPath = $shadowPath;
    }

    public function getOverlayPath(): string { return $this->overlayPath; }
    public function getMaskPath(): string { return $this->maskPath; }
    public function getShadowPath(): string { return $this->shadowPath; }
}
```

```php
<?php
// src/Slide/SlideOptions.php
namespace Phgors\GoCaptcha\Slide;

use Phgors\GoCaptcha\Base\Size;
use Phgors\GoCaptcha\Base\RangeVal;

final class SlideOptions
{
    private Size $imageSize;
    private RangeVal $rangeGraphSize;
    private int $graphNumber;
    private bool $enableGraphVerticalRandom;
    private bool $regionMode;

    public function __construct()
    {
        $this->imageSize = new Size(300, 220);
        $this->rangeGraphSize = new RangeVal(50, 70);
        $this->graphNumber = 1;
        $this->enableGraphVerticalRandom = false;
        $this->regionMode = false;
    }

    public function getImageSize(): Size { return $this->imageSize; }
    public function getRangeGraphSize(): RangeVal { return $this->rangeGraphSize; }
    public function getGraphNumber(): int { return $this->graphNumber; }
    public function isEnableGraphVerticalRandom(): bool { return $this->enableGraphVerticalRandom; }
    public function isRegionMode(): bool { return $this->regionMode; }

    public function withImageSize(Size $s): self { $c = clone $this; $c->imageSize = $s; return $c; }
    public function withRangeGraphSize(RangeVal $r): self { $c = clone $this; $c->rangeGraphSize = $r; return $c; }
    public function withGraphNumber(int $n): self { $c = clone $this; $c->graphNumber = $n; return $c; }
    public function withEnableGraphVerticalRandom(bool $v): self { $c = clone $this; $c->enableGraphVerticalRandom = $v; return $c; }
    public function withRegionMode(bool $v): self { $c = clone $this; $c->regionMode = $v; return $c; }
}
```

```php
<?php
// src/Slide/SlideCaptchaData.php
namespace Phgors\GoCaptcha\Slide;

use Phgors\GoCaptcha\ImageData\JpegImage;
use Phgors\GoCaptcha\ImageData\PngImage;

final class SlideCaptchaData
{
    private Block $block;
    private JpegImage $masterImage;
    private PngImage $tileImage;

    public function __construct(Block $block, JpegImage $masterImage, PngImage $tileImage)
    {
        $this->block = $block;
        $this->masterImage = $masterImage;
        $this->tileImage = $tileImage;
    }

    public function getBlock(): Block { return $this->block; }
    public function getMasterImage(): JpegImage { return $this->masterImage; }
    public function getTileImage(): PngImage { return $this->tileImage; }

    public function toArray(): array
    {
        return [
            'masterImage' => $this->masterImage->toBase64(),
            'tileImage'   => $this->tileImage->toBase64(),
        ];
    }
}
```

- [ ] **Step 3：运行验证通过**

```bash
vendor/bin/phpunit tests/Slide/SlideModelTest.php
```

- [ ] **Step 4：提交**

```bash
git add src/Slide tests/Slide
git commit -m "feat: 添加滑动验证码数据模型"
```

---

### Task 4.2：MaskProcessor 像素级 alpha 合成（TDD，核心难点）

**Files:**
- Create: `src/Slide/MaskProcessor.php`
- Test: `tests/Slide/MaskProcessorTest.php`

> MaskProcessor 作用：给定一个目标画布区域和一张灰度 mask + 源图，按 mask 灰度计算每个像素的 alpha，合成到目标上。提供两个核心方法：
> - `cutHole(Canvas $master, Canvas $overlay, Canvas $mask, int $x, int $y)`：在主图上挖缺口（暗化 + 用 overlay 像素按 mask alpha 混合）
> - `applyAlpha(Canvas $dest, Canvas $overlay, Canvas $mask)`：生成带透明通道的拼图块（mask 白处不透明，黑处透明）

- [ ] **Step 1：写测试（构造灰度 mask + 验证透明像素）**

```php
<?php
// tests/Slide/MaskProcessorTest.php
namespace Phgors\GoCaptcha\Tests\Slide;

use Phgors\GoCaptcha\Base\Canvas;
use Phgors\GoCaptcha\Slide\MaskProcessor;
use PHPUnit\Framework\TestCase;

class MaskProcessorTest extends TestCase
{
    public function test_apply_alpha_makes_black_mask_area_transparent(): void
    {
        $size = 8;
        $overlay = new Canvas($size, $size);
        $overlayCol = $overlay->allocateColor(\Phgors\GoCaptcha\Base\Color::fromHex('#ff0000'));
        imagefilledrectangle($overlay->getResource(), 0, 0, $size, $size, $overlayCol);

        // mask：上半白(255)下半黑(0)
        $mask = new Canvas($size, $size);
        $white = $mask->allocateColor(\Phgors\GoCaptcha\Base\Color::fromHex('#ffffff'));
        $black = $mask->allocateColor(\Phgors\GoCaptcha\Base\Color::fromHex('#000000'));
        imagefilledrectangle($mask->getResource(), 0, 0, $size, $size / 2, $white);
        imagefilledrectangle($mask->getResource(), 0, $size / 2, $size, $size, $black);

        $dest = new Canvas($size, $size);
        (new MaskProcessor())->applyAlpha($dest, $overlay, $mask);

        // 上半（mask 白）应不透明：alpha 期望接近 0
        self::assertLessThan(30, $dest->getAlphaAt(2, 1));
        // 下半（mask 黑）应接近全透明：alpha 期望接近 127
        self::assertGreaterThan(100, $dest->getAlphaAt(2, 6));

        $overlay->destroy(); $mask->destroy(); $dest->destroy();
    }
}
```

- [ ] **Step 2：运行验证失败**

- [ ] **Step 3：实现 MaskProcessor**

```php
<?php
// src/Slide/MaskProcessor.php
namespace Phgors\GoCaptcha\Slide;

use Phgors\GoCaptcha\Base\Canvas;

final class MaskProcessor
{
    /**
     * 将 overlay 按 mask 灰度合成为带透明通道的拼图块写入 dest。
     * mask 越白 -> 越不透明；mask 越黑 -> 越透明。
     */
    public function applyAlpha(Canvas $dest, Canvas $overlay, Canvas $mask): void
    {
        $w = min($dest->getWidth(), $overlay->getWidth(), $mask->getWidth());
        $h = min($dest->getHeight(), $overlay->getHeight(), $mask->getHeight());
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $rgb = imagecolorat($overlay->getResource(), $x, $y);
                if ($rgb === false) {
                    continue;
                }
                $maskRgb = imagecolorat($mask->getResource(), $x, $y);
                if ($maskRgb === false) {
                    continue;
                }
                $maskGray = (int)(($maskRgb >> 16) & 0xFF);
                $alpha = (int)(127 - ($maskGray / 255) * 127);
                $r = (int)(($rgb >> 16) & 0xFF);
                $g = (int)(($rgb >> 8) & 0xFF);
                $b = (int)($rgb & 0xFF);
                $color = imagecolorallocatealpha($dest->getResource(), $r, $g, $b, $alpha);
                if ($color !== false) {
                    imagesetpixel($dest->getResource(), $x, $y, $color);
                }
            }
        }
    }

    /**
     * 在主图 (x,y) 处挖缺口：shadow 半透明阴影 + overlay 按 mask alpha 混合（白处显示缺口像素）。
     */
    public function cutHole(Canvas $master, Canvas $overlay, Canvas $mask, Canvas $shadow, int $x, int $y): void
    {
        $w = min($overlay->getWidth(), $mask->getWidth(), $shadow->getWidth());
        $h = min($overlay->getHeight(), $mask->getHeight(), $shadow->getHeight());
        for ($j = 0; $j < $h; $j++) {
            for ($i = 0; $i < $w; $i++) {
                $mx = $x + $i;
                $my = $y + $j;
                if ($mx < 0 || $my < 0 || $mx >= $master->getWidth() || $my >= $master->getHeight()) {
                    continue;
                }
                $maskRgb = imagecolorat($mask->getResource(), $i, $j);
                if ($maskRgb === false) {
                    continue;
                }
                $maskGray = (int)(($maskRgb >> 16) & 0xFF);
                if ($maskGray < 30) {
                    continue;
                }

                // 阴影：按 shadow 灰度叠暗色
                $shadowRgb = imagecolorat($shadow->getResource(), $i, $j);
                if ($shadowRgb !== false) {
                    $sGray = (int)(($shadowRgb >> 16) & 0xFF);
                    $sAlpha = (int)(127 - ($sGray / 255) * 90);
                    $sCol = imagecolorallocatealpha($master->getResource(), 0, 0, 0, $sAlpha);
                    if ($sCol !== false) {
                        imagesetpixel($master->getResource(), $mx, $my, $sCol);
                    }
                }
            }
        }
    }
}
```

- [ ] **Step 4：运行验证通过**

```bash
vendor/bin/phpunit tests/Slide/MaskProcessorTest.php
```

- [ ] **Step 5：提交**

```bash
git add src/Slide/MaskProcessor.php tests/Slide/MaskProcessorTest.php
git commit -m "feat: 实现 MaskProcessor 像素级 alpha 合成"
```

---

### Task 4.3：SlideBuilder + SlideCaptcha + 生成核心（TDD，需内置素材）

> 前置：阶段 5 Task 5.2（放入内置拼图素材）。

**Files:**
- Create: `src/Slide/SlideBuilder.php`
- Create: `src/Slide/SlideCaptcha.php`
- Create: `src/Slide/SlideGenerator.php`
- Test: `tests/Slide/SlideGenerateTest.php`

- [ ] **Step 1：写测试**

```php
<?php
// tests/Slide/SlideGenerateTest.php
namespace Phgors\GoCaptcha\Tests\Slide;

use Phgors\GoCaptcha\Slide\SlideBuilder;
use Phgors\GoCaptcha\Slide\SlideCaptchaData;
use Phgors\GoCaptcha\Assets\DefaultAssets;
use PHPUnit\Framework\TestCase;

class SlideGenerateTest extends TestCase
{
    public function test_generate_returns_master_and_tile(): void
    {
        $graphSets = DefaultAssets::tileSets();
        if ($graphSets === []) {
            self::markTestSkipped('未提供拼图素材，跳过');
        }
        $graphs = array_map(function ($dir) {
            return new \Phgors\GoCaptcha\Slide\GraphImage(
                $dir . '/overlay.png',
                $dir . '/mask.png',
                $dir . '/shadow.png'
            );
        }, $graphSets);

        $captcha = SlideBuilder::make()
            ->setBackgrounds(DefaultAssets::backgrounds())
            ->setGraphs($graphs)
            ->build();

        $data = $captcha->generate();

        self::assertInstanceOf(SlideCaptchaData::class, $data);
        self::assertStringStartsWith('data:image/jpeg;base64,', $data->getMasterImage()->toBase64());
        self::assertStringStartsWith('data:image/png;base64,', $data->getTileImage()->toBase64());
        self::assertGreaterThan(0, $data->getBlock()->getX());
    }
}
```

- [ ] **Step 2：实现 SlideBuilder**

```php
<?php
// src/Slide/SlideBuilder.php
namespace Phgors\GoCaptcha\Slide;

use Phgors\GoCaptcha\Base\Rng;
use Phgors\GoCaptcha\Exception\ResourceException;

final class SlideBuilder
{
    private SlideOptions $options;
    /** @var GraphImage[] */
    private array $graphs = [];
    /** @var string[] */
    private array $backgrounds = [];
    private ?Rng $rng = null;
    private bool $regionMode = false;

    public function __construct()
    {
        $this->options = new SlideOptions();
    }

    public static function make(): self { return new self(); }

    public function setOptions(SlideOptions $o): self { $this->options = $o; return $this; }

    /** @param string[] $backgrounds */
    public function setBackgrounds(array $backgrounds): self { $this->backgrounds = $backgrounds; return $this; }

    /** @param GraphImage[] $graphs */
    public function setGraphs(array $graphs): self { $this->graphs = $graphs; return $this; }

    public function setRng(Rng $rng): self { $this->rng = $rng; return $this; }

    public function build(): SlideCaptcha
    {
        $this->validate();
        $opts = $this->regionMode ? $this->options->withRegionMode(true) : $this->options;
        return new SlideCaptcha($opts, $this->graphs, $this->backgrounds, $this->rng ?? new Rng());
    }

    public function buildRegion(): SlideCaptcha
    {
        $this->regionMode = true;
        return $this->build();
    }

    private function validate(): void
    {
        if ($this->backgrounds === []) {
            throw new ResourceException('滑动验证码必须提供背景图');
        }
        if ($this->graphs === []) {
            throw new ResourceException('滑动验证码必须提供拼图素材');
        }
    }
}
```

- [ ] **Step 3：实现 SlideCaptcha + SlideGenerator**

```php
<?php
// src/Slide/SlideCaptcha.php
namespace Phgors\GoCaptcha\Slide;

use Phgors\GoCaptcha\Base\Rng;

final class SlideCaptcha
{
    private SlideOptions $options;
    /** @var GraphImage[] */
    private array $graphs;
    /** @var string[] */
    private array $backgrounds;
    private Rng $rng;

    /**
     * @param GraphImage[] $graphs
     * @param string[] $backgrounds
     */
    public function __construct(SlideOptions $options, array $graphs, array $backgrounds, Rng $rng)
    {
        $this->options = $options;
        $this->graphs = $graphs;
        $this->backgrounds = $backgrounds;
        $this->rng = $rng;
    }

    public function generate(): SlideCaptchaData
    {
        return (new SlideGenerator($this->options, $this->graphs, $this->backgrounds, $this->rng))->generate();
    }
}
```

```php
<?php
// src/Slide/SlideGenerator.php
namespace Phgors\GoCaptcha\Slide;

use Phgors\GoCaptcha\Base\Canvas;
use Phgors\GoCaptcha\Base\Color;
use Phgors\GoCaptcha\Base\Rng;
use Phgors\GoCaptcha\Exception\GenerationException;
use Phgors\GoCaptcha\ImageData\JpegImage;
use Phgors\GoCaptcha\ImageData\PngImage;

final class SlideGenerator
{
    private SlideOptions $options;
    /** @var GraphImage[] */
    private array $graphs;
    /** @var string[] */
    private array $backgrounds;
    private Rng $rng;

    /**
     * @param GraphImage[] $graphs
     * @param string[] $backgrounds
     */
    public function __construct(SlideOptions $options, array $graphs, array $backgrounds, Rng $rng)
    {
        $this->options = $options;
        $this->graphs = $graphs;
        $this->backgrounds = $backgrounds;
        $this->rng = $rng;
    }

    public function generate(): SlideCaptchaData
    {
        $imgSize = $this->options->getImageSize();
        $graph = $this->rng->pick($this->graphs);

        $overlay = Canvas::fromPath($graph->getOverlayPath());
        $mask = Canvas::fromPath($graph->getMaskPath());
        $shadow = Canvas::fromPath($graph->getShadowPath());

        $tileW = $overlay->getWidth();
        $tileH = $overlay->getHeight();

        // 缩放拼图块到 rangeGraphSize 范围
        $targetSize = $this->rng->range($this->options->getRangeGraphSize());
        $scaledOverlay = new Canvas($targetSize, $targetSize);
        $scaledMask = new Canvas($targetSize, $targetSize);
        $scaledShadow = new Canvas($targetSize, $targetSize);
        $scaledOverlay->copyResampled($overlay, 0, 0, 0, 0, $targetSize, $targetSize, $tileW, $tileH);
        $scaledMask->copyResampled($mask, 0, 0, 0, 0, $targetSize, $targetSize, $tileW, $tileH);
        $scaledShadow->copyResampled($shadow, 0, 0, 0, 0, $targetSize, $targetSize, $tileW, $tileH);
        $overlay->destroy(); $mask->destroy(); $shadow->destroy();

        // 主图
        $bgPath = $this->rng->pick($this->backgrounds);
        $bg = Canvas::fromPath($bgPath);
        $master = new Canvas($imgSize->getWidth(), $imgSize->getHeight());
        $master->copyResampled($bg, 0, 0, 0, 0, $imgSize->getWidth(), $imgSize->getHeight(), $bg->getWidth(), $bg->getHeight());
        $bg->destroy();

        // 目标位置
        $tx = $this->rng->getInt($targetSize + 10, max($targetSize + 11, $imgSize->getWidth() - $targetSize - 10));
        if ($this->options->isRegionMode() || $this->options->isEnableGraphVerticalRandom()) {
            $ty = $this->rng->getInt(10, max(11, $imgSize->getHeight() - $targetSize - 10));
        } else {
            $ty = $imgSize->getHeight() - $targetSize - (int)($targetSize * 0.2);
        }
        $angle = $this->rng->getInt(0, 360);

        // 挖缺口
        (new MaskProcessor())->cutHole($master, $scaledOverlay, $scaledMask, $scaledShadow, $tx, $ty);

        // 生成拼图块（带 alpha）
        $tile = new Canvas($targetSize, $targetSize);
        (new MaskProcessor())->applyAlpha($tile, $scaledOverlay, $scaledMask);

        $scaledOverlay->destroy(); $scaledMask->destroy(); $scaledShadow->destroy();

        $jpeg = new JpegImage($master->releaseResource());
        $png = new PngImage($tile->releaseResource());
        $block = new Block($tx, $ty, $targetSize, $targetSize, $angle);

        return new SlideCaptchaData($block, $jpeg, $png);
    }
}
```

- [ ] **Step 4：运行验证通过**

```bash
vendor/bin/phpunit tests/Slide/SlideGenerateTest.php
```

- [ ] **Step 5：提交**

```bash
git add src/Slide tests/Slide
git commit -m "feat: 实现滑动验证码生成核心"
```

---

### Task 4.4：SlideValidator（TDD）

**Files:**
- Create: `src/Slide/SlideValidator.php`
- Test: `tests/Slide/SlideValidatorTest.php`

- [ ] **Step 1：写测试**

```php
<?php
// tests/Slide/SlideValidatorTest.php
namespace Phgors\GoCaptcha\Tests\Slide;

use Phgors\GoCaptcha\Slide\Block;
use Phgors\GoCaptcha\Slide\SlideValidator;
use PHPUnit\Framework\TestCase;

class SlideValidatorTest extends TestCase
{
    public function test_validate_within_padding(): void
    {
        $block = new Block(100, 100, 60, 60, 0);
        self::assertTrue(SlideValidator::validate($block, 108, 105, 10));
    }

    public function test_validate_outside_padding(): void
    {
        $block = new Block(100, 100, 60, 60, 0);
        self::assertFalse(SlideValidator::validate($block, 130, 100, 10));
    }
}
```

- [ ] **Step 2：运行验证失败**

- [ ] **Step 3：实现 SlideValidator**

```php
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
```

- [ ] **Step 4：运行验证通过**

```bash
vendor/bin/phpunit tests/Slide/SlideValidatorTest.php
```

- [ ] **Step 5：提交**

```bash
git add src/Slide/SlideValidator.php tests/Slide/SlideValidatorTest.php
git commit -m "feat: 实现滑动验证码校验器"
```

---

# 阶段 5：旋转验证码（Rotate）

### Task 5.1：RotateBlock / RotateOptions / RotateCaptchaData（TDD）

**Files:**
- Create: `src/Rotate/RotateBlock.php`
- Create: `src/Rotate/RotateOptions.php`
- Create: `src/Rotate/RotateCaptchaData.php`
- Test: `tests/Rotate/RotateModelTest.php`

- [ ] **Step 1：写测试**

```php
<?php
// tests/Rotate/RotateModelTest.php
namespace Phgors\GoCaptcha\Tests\Rotate;

use Phgors\GoCaptcha\Rotate\RotateBlock;
use Phgors\GoCaptcha\Rotate\RotateOptions;
use PHPUnit\Framework\TestCase;

class RotateModelTest extends TestCase
{
    public function test_block_holds_angle(): void
    {
        self::assertSame(90, (new RotateBlock(90))->getAngle());
    }

    public function test_options_defaults(): void
    {
        $o = new RotateOptions();
        self::assertSame(220, $o->getImageSquareSize());
        self::assertSame(0, $o->getRangeAngle()->getMin());
        self::assertSame(360, $o->getRangeAngle()->getMax());
    }
}
```

- [ ] **Step 2：实现三个类**

```php
<?php
// src/Rotate/RotateBlock.php
namespace Phgors\GoCaptcha\Rotate;

final class RotateBlock
{
    private int $angle;

    public function __construct(int $angle)
    {
        $this->angle = $angle;
    }

    public function getAngle(): int { return $this->angle; }

    public function toArray(): array { return ['angle' => $this->angle]; }
}
```

```php
<?php
// src/Rotate/RotateOptions.php
namespace Phgors\GoCaptcha\Rotate;

use Phgors\GoCaptcha\Base\RangeVal;

final class RotateOptions
{
    private int $imageSquareSize;
    private int $thumbSquareSize;
    private RangeVal $rangeAngle;
    private float $thumbAlpha;

    public function __construct()
    {
        $this->imageSquareSize = 220;
        $this->thumbSquareSize = 150;
        $this->rangeAngle = new RangeVal(0, 360);
        $this->thumbAlpha = 1.0;
    }

    public function getImageSquareSize(): int { return $this->imageSquareSize; }
    public function getThumbSquareSize(): int { return $this->thumbSquareSize; }
    public function getRangeAngle(): RangeVal { return $this->rangeAngle; }
    public function getThumbAlpha(): float { return $this->thumbAlpha; }

    public function withImageSquareSize(int $v): self { $c = clone $this; $c->imageSquareSize = $v; return $c; }
    public function withThumbSquareSize(int $v): self { $c = clone $this; $c->thumbSquareSize = $v; return $c; }
    public function withRangeAngle(RangeVal $r): self { $c = clone $this; $c->rangeAngle = $r; return $c; }
    public function withThumbAlpha(float $v): self { $c = clone $this; $c->thumbAlpha = $v; return $c; }
}
```

```php
<?php
// src/Rotate/RotateCaptchaData.php
namespace Phgors\GoCaptcha\Rotate;

use Phgors\GoCaptcha\ImageData\JpegImage;
use Phgors\GoCaptcha\ImageData\PngImage;

final class RotateCaptchaData
{
    private RotateBlock $block;
    private JpegImage $masterImage;
    private PngImage $thumbImage;

    public function __construct(RotateBlock $block, JpegImage $masterImage, PngImage $thumbImage)
    {
        $this->block = $block;
        $this->masterImage = $masterImage;
        $this->thumbImage = $thumbImage;
    }

    public function getBlock(): RotateBlock { return $this->block; }
    public function getMasterImage(): JpegImage { return $this->masterImage; }
    public function getThumbImage(): PngImage { return $this->thumbImage; }

    public function toArray(): array
    {
        return [
            'masterImage' => $this->masterImage->toBase64(),
            'thumbImage'  => $this->thumbImage->toBase64(),
        ];
    }
}
```

- [ ] **Step 3：运行验证通过**

```bash
vendor/bin/phpunit tests/Rotate/RotateModelTest.php
```

- [ ] **Step 4：提交**

```bash
git add src/Rotate tests/Rotate
git commit -m "feat: 添加旋转验证码数据模型"
```

---

### Task 5.2：RotateBuilder + RotateCaptcha + 生成核心（TDD）

**Files:**
- Create: `src/Rotate/RotateBuilder.php`
- Create: `src/Rotate/RotateCaptcha.php`
- Create: `src/Rotate/RotateGenerator.php`
- Test: `tests/Rotate/RotateGenerateTest.php`

- [ ] **Step 1：写测试**

```php
<?php
// tests/Rotate/RotateGenerateTest.php
namespace Phgors\GoCaptcha\Tests\Rotate;

use Phgors\GoCaptcha\Rotate\RotateBuilder;
use Phgors\GoCaptcha\Rotate\RotateCaptchaData;
use Phgors\GoCaptcha\Assets\DefaultAssets;
use PHPUnit\Framework\TestCase;

class RotateGenerateTest extends TestCase
{
    public function test_generate_returns_master_and_thumb(): void
    {
        if (DefaultAssets::backgrounds() === []) {
            self::markTestSkipped('未提供背景素材');
        }
        $captcha = RotateBuilder::make()
            ->setBackgrounds(DefaultAssets::backgrounds())
            ->build();

        $data = $captcha->generate();

        self::assertInstanceOf(RotateCaptchaData::class, $data);
        self::assertStringStartsWith('data:image/jpeg;base64,', $data->getMasterImage()->toBase64());
        self::assertStringStartsWith('data:image/png;base64,', $data->getThumbImage()->toBase64());
        self::assertGreaterThanOrEqual(0, $data->getBlock()->getAngle());
        self::assertLessThanOrEqual(360, $data->getBlock()->getAngle());
    }
}
```

- [ ] **Step 2：实现 RotateBuilder / RotateCaptcha / RotateGenerator**

```php
<?php
// src/Rotate/RotateBuilder.php
namespace Phgors\GoCaptcha\Rotate;

use Phgors\GoCaptcha\Base\Rng;
use Phgors\GoCaptcha\Exception\ResourceException;

final class RotateBuilder
{
    private RotateOptions $options;
    /** @var string[] */
    private array $backgrounds = [];
    private ?Rng $rng = null;

    public function __construct() { $this->options = new RotateOptions(); }

    public static function make(): self { return new self(); }

    public function setOptions(RotateOptions $o): self { $this->options = $o; return $this; }

    /** @param string[] $backgrounds */
    public function setBackgrounds(array $backgrounds): self { $this->backgrounds = $backgrounds; return $this; }

    public function setRng(Rng $rng): self { $this->rng = $rng; return $this; }

    public function build(): RotateCaptcha
    {
        if ($this->backgrounds === []) {
            throw new ResourceException('旋转验证码必须提供背景图');
        }
        return new RotateCaptcha($this->options, $this->backgrounds, $this->rng ?? new Rng());
    }
}
```

```php
<?php
// src/Rotate/RotateCaptcha.php
namespace Phgors\GoCaptcha\Rotate;

use Phgors\GoCaptcha\Base\Rng;

final class RotateCaptcha
{
    private RotateOptions $options;
    /** @var string[] */
    private array $backgrounds;
    private Rng $rng;

    /** @param string[] $backgrounds */
    public function __construct(RotateOptions $options, array $backgrounds, Rng $rng)
    {
        $this->options = $options;
        $this->backgrounds = $backgrounds;
        $this->rng = $rng;
    }

    public function generate(): RotateCaptchaData
    {
        return (new RotateGenerator($this->options, $this->backgrounds, $this->rng))->generate();
    }
}
```

```php
<?php
// src/Rotate/RotateGenerator.php
namespace Phgors\GoCaptcha\Rotate;

use Phgors\GoCaptcha\Base\Canvas;
use Phgors\GoCaptcha\Base\Color;
use Phgors\GoCaptcha\Base\Rng;
use Phgors\GoCaptcha\ImageData\JpegImage;
use Phgors\GoCaptcha\ImageData\PngImage;

final class RotateGenerator
{
    private RotateOptions $options;
    /** @var string[] */
    private array $backgrounds;
    private Rng $rng;

    /** @param string[] $backgrounds */
    public function __construct(RotateOptions $options, array $backgrounds, Rng $rng)
    {
        $this->options = $options;
        $this->backgrounds = $backgrounds;
        $this->rng = $rng;
    }

    public function generate(): RotateCaptchaData
    {
        $size = $this->options->getImageSquareSize();
        $thumbSize = $this->options->getThumbSquareSize();
        $angle = $this->rng->range($this->options->getRangeAngle());

        // 载入背景，按短边裁正方形
        $bgPath = $this->rng->pick($this->backgrounds);
        $bg = Canvas::fromPath($bgPath);
        $side = min($bg->getWidth(), $bg->getHeight());
        $offsetX = (int)(($bg->getWidth() - $side) / 2);
        $offsetY = (int)(($bg->getHeight() - $side) / 2);
        $square = new Canvas($side, $side);
        $square->copy($bg, 0, 0, $offsetX, $offsetY, $side, $side);
        $bg->destroy();

        // 缩放到目标尺寸
        $resized = new Canvas($size, $size);
        $resized->copyResampled($square, 0, 0, 0, 0, $size, $size, $side, $side);
        $square->destroy();

        // 主图：按 angle 旋转
        $bgColor = $resized->allocateColor(Color::fromHex('#000000'));
        $rotated = $resized->rotate((float)$angle, $bgColor);
        // 旋转后画布变大，裁回 size×size 中心
        $master = new Canvas($size, $size);
        $rx = (int)(($rotated->getWidth() - $size) / 2);
        $ry = (int)(($rotated->getHeight() - $size) / 2);
        $master->copy($rotated, 0, 0, max(0, $rx), max(0, $ry), $size, $size);
        $rotated->destroy();

        // 缩略图：从主图中心裁圆形
        $thumb = $this->makeCircularThumb($master, $thumbSize);

        $jpeg = new JpegImage($master->releaseResource());
        $png = new PngImage($thumb->releaseResource());
        $block = new RotateBlock($angle);

        $resized->destroy();

        return new RotateCaptchaData($block, $jpeg, $png);
    }

    private function makeCircularThumb(Canvas $master, int $thumbSize): Canvas
    {
        $w = $master->getWidth();
        $thumb = new Canvas($thumbSize, $thumbSize);
        // 居中裁方形再缩放
        $srcSide = $w;
        $thumb->copyResampled($master, 0, 0, 0, 0, $thumbSize, $thumbSize, $srcSide, $srcSide);

        // 应用圆形 alpha 蒙版
        $center = $thumbSize / 2;
        $radius = $center;
        for ($y = 0; $y < $thumbSize; $y++) {
            for ($x = 0; $x < $thumbSize; $x++) {
                $dx = $x - $center;
                $dy = $y - $center;
                $dist = sqrt($dx * $dx + $dy * $dy);
                if ($dist > $radius) {
                    $col = imagecolorallocatealpha($thumb->getResource(), 0, 0, 0, 127);
                    if ($col !== false) {
                        imagesetpixel($thumb->getResource(), $x, $y, $col);
                    }
                } elseif ($dist > $radius - 1.5) {
                    // 边缘抗锯齿
                    $a = 127 - (int)((1 - ($radius - $dist) / 1.5) * 127);
                    $rgb = imagecolorat($thumb->getResource(), $x, $y);
                    $r = (int)(($rgb >> 16) & 0xFF);
                    $g = (int)(($rgb >> 8) & 0xFF);
                    $b = (int)($rgb & 0xFF);
                    $col = imagecolorallocatealpha($thumb->getResource(), $r, $g, $b, max(0, min(127, $a)));
                    if ($col !== false) {
                        imagesetpixel($thumb->getResource(), $x, $y, $col);
                    }
                }
            }
        }
        return $thumb;
    }
}
```

- [ ] **Step 3：运行验证通过**

```bash
vendor/bin/phpunit tests/Rotate/RotateGenerateTest.php
```

- [ ] **Step 4：提交**

```bash
git add src/Rotate tests/Rotate
git commit -m "feat: 实现旋转验证码生成核心"
```

---

### Task 5.3：RotateValidator（TDD，环形角度）

**Files:**
- Create: `src/Rotate/RotateValidator.php`
- Test: `tests/Rotate/RotateValidatorTest.php`

- [ ] **Step 1：写测试**

```php
<?php
// tests/Rotate/RotateValidatorTest.php
namespace Phgors\GoCaptcha\Tests\Rotate;

use Phgors\GoCaptcha\Rotate\RotateValidator;
use PHPUnit\Framework\TestCase;

class RotateValidatorTest extends TestCase
{
    public function test_within_padding(): void
    {
        self::assertTrue(RotateValidator::validate(100, 108, 10));
    }

    public function test_wraparound_0_360(): void
    {
        self::assertTrue(RotateValidator::validate(358, 2, 10));
        self::assertTrue(RotateValidator::validate(2, 358, 10));
    }

    public function test_outside_padding(): void
    {
        self::assertFalse(RotateValidator::validate(100, 130, 10));
    }
}
```

- [ ] **Step 2：运行验证失败**

- [ ] **Step 3：实现 RotateValidator**

```php
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
```

- [ ] **Step 4：运行验证通过**

```bash
vendor/bin/phpunit tests/Rotate/RotateValidatorTest.php
```

- [ ] **Step 5：提交**

```bash
git add src/Rotate/RotateValidator.php tests/Rotate/RotateValidatorTest.php
git commit -m "feat: 实现旋转验证码校验器（环形角度）"
```

---

# 阶段 6：内置素材与文档

### Task 6.1：放入内置素材（字体/背景/拼图）

**Files:**
- Create: `resources/fonts/SourceHanSansSC-Regular.otf`（思源黑体子集，OFL）
- Create: `resources/backgrounds/*.jpg`（~5-10 张，CC0 自然图）
- Create: `resources/tiles/<set>/{overlay,mask,shadow}.png`（~3 套拼图）

- [ ] **Step 1：下载/准备素材**

- 字体：从 https://github.com/paladinite/fonts-cjk 或思源黑体 release 下载子集 OTF/TTF（OFL-1.1 协议），重命名为 `SourceHanSansSC-Regular.otf`，放入 `resources/fonts/`。若体积过大，用 fonttools subset 工具保留 `resources/chars.php` 中字符 + ASCII 做子集化。
- 背景：从 CC0 图库（如 Pexels/Unsplash，确认协议）选 5-10 张自然/抽象图，缩放至约 600×440，存为 `resources/backgrounds/bg_XX.jpg`，单张 < 100KB。
- 拼图：制作 3 套 `{overlay,mask,shadow}.png`（每张约 60×60）。可参考 go-captcha-assets 的素材格式。`overlay` 为彩色拼图块，`mask` 为白色形状灰度蒙版（形状内白、外黑），`shadow` 为阴影灰度蒙版。

- [ ] **Step 2：校验素材可被加载**

```bash
php -r "require 'vendor/autoload.php'; var_dump(count(\Phgors\GoCaptcha\Assets\DefaultAssets::backgrounds())); var_dump(count(\Phgors\GoCaptcha\Assets\DefaultAssets::fonts())); var_dump(count(\Phgors\GoCaptcha\Assets\tileSets()));"
```
预期：三个数字均 ≥1。

- [ ] **Step 3：跑全部测试确认端到端可用**

```bash
vendor/bin/phpunit
```
预期：全部 PASS。

- [ ] **Step 4：提交**

```bash
git add resources
git commit -m "feat: 添加内置默认素材（字体/背景/拼图）"
```

---

### Task 6.2：README + CHANGELOG

**Files:**
- Create: `README.md`
- Create: `CHANGELOG.md`

- [ ] **Step 1：写 README（含三种验证码完整示例）**

```markdown
# phgors/gocaptcha

PHP 行为验证码库（点选 / 滑动 / 旋转），[go-captcha](https://github.com/wenlng/go-captcha) 生态的 PHP 后端实现。基于 GD，开箱即用，内置默认素材。

## 安装

\```bash
composer require phgors/gocaptcha
\```

要求：PHP >= 7.4，ext-gd（建议启用 FreeType）。

## 点选验证码

\```php
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
// $data->getDots() 自行存 Session/Cache 作为答案

// 校验（下一次请求，用户点击的点）
$ok = ClickValidator::validate($storedDots, $userPoints, $padding = 10);
\```

## 滑动验证码

\```php
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
    ->build(); // 或 ->buildRegion() 拖拽模式

$data = $captcha->generate();
$payload = $data->toArray();     // masterImage + tileImage
$block = $data->getBlock();      // 存 Session/Cache

$ok = SlideValidator::validate($block, $userX, $userY, $padding = 5);
\```

## 旋转验证码

\```php
use Phgors\GoCaptcha\Rotate\RotateBuilder;
use Phgors\GoCaptcha\Assets\DefaultAssets;
use Phgors\GoCaptcha\Rotate\RotateValidator;

$captcha = RotateBuilder::make()
    ->setBackgrounds(DefaultAssets::backgrounds())
    ->build();

$data = $captcha->generate();
$payload = $data->toArray();
$angle = $data->getBlock()->getAngle(); // 存 Session/Cache

$ok = RotateValidator::validate($angle, $userAngle, $padding = 8);
\```

## 自定义素材

实现 `Phgors\GoCaptcha\Assets\AssetLoader` 自定义路径，或直接把素材绝对路径传入对应 Builder 的 setter。

## 协议

Apache-2.0。内置字体遵循 SIL OFL-1.1。
```

- [ ] **Step 2：写 CHANGELOG**

```markdown
# CHANGELOG

## 1.0.0 - 2026-06-18

- 首版发布
- 点选验证码（文本/图形双模式）
- 滑动验证码（基本/拖拽模式）
- 旋转验证码（圆形缩略图）
- 内置默认字体/背景/拼图素材
- 纯无状态，框架无关
```

- [ ] **Step 3：提交**

```bash
git add README.md CHANGELOG.md
git commit -m "docs: 添加 README 与 CHANGELOG"
```

---

### Task 6.3：CI（GitHub Actions）

**Files:**
- Create: `.github/workflows/tests.yml`

- [ ] **Step 1：写 CI 配置**

```yaml
# .github/workflows/tests.yml
name: tests

on:
  push:
    branches: [main]
  pull_request:

jobs:
  test:
    runs-on: ubuntu-latest
    strategy:
      matrix:
        php: ['7.4', '8.0', '8.1', '8.2', '8.3']
    steps:
      - uses: actions/checkout@v4
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
          extensions: gd
          coverage: none
      - name: Install Composer
        run: composer update --prefer-dist --no-interaction
      - name: Run tests
        run: vendor/bin/phpunit
```

- [ ] **Step 2：提交**

```bash
git add .github
git commit -m "ci: 添加 PHPUnit 多版本测试矩阵"
```

---

### Task 6.4：发布 v1.0.0

- [ ] **Step 1：全量回归**

```bash
vendor/bin/phpunit
```
预期：全部 PASS。

- [ ] **Step 2：打 tag 并推送**

```bash
git tag v1.0.0
git push origin main --tags
```

- [ ] **Step 3：提交到 packagist（手动）**

- 访问 https://packagist.org/explore/ 登录
- Submit：粘贴 `https://github.com/phgors/gocaptcha`
- 设置 GitHub webhook 自动更新（packagist 提示设置）

---

## 自审清单

**1. Spec 覆盖：**
- [x] 三种验证码生成 → Task 3.3 / 4.3 / 5.2
- [x] 三种校验 → Task 3.4 / 4.4 / 5.3
- [x] Base 层（Size/Point/RangeVal/Color/Rng/Canvas/Distortion/ImageEncoder）→ Task 1.2-1.7
- [x] ImageData（Jpeg/Png）→ Task 1.6
- [x] 异常层级 → Task 1.1
- [x] Assets（Font/Loader/DefaultAssets）→ Task 2.1
- [x] 内置素材 → Task 6.1
- [x] 无状态设计 → 所有 Validator 为静态纯函数；Data::toArray 不含答案
- [x] 打包发布 → Task 0.1 / 6.4
- [x] CI → Task 6.3
- [x] 测试策略 → 全程 TDD + Task 6.4 回归

**2. 类型/命名一致性：**
- 命名空间 `Phgors\GoCaptcha\` 全文一致
- 图像格式：主图 JPEG、缩略图/拼图 PNG 全文一致
- Builder `make()` / `build()` / `setRng()` 三个验证码一致
- Validator 静态方法 `validate` 一致；Click 额外 `checkPoint`
- `Data::toArray()` 仅含图像 base64，三者一致

**3. 已知简化（相对 Go，记入后续演进）：**
- 扭曲等级未细分级（Distortion 提供基础噪点/线/圆，参数化程度低于 Go 的 DistortLevel1-5）
- 拼图未实现多图形个数（graphNumber>1）与盲区方向约束
- 资源内嵌字体子集化由 Task 6.1 手动处理
