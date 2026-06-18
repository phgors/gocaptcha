# phgors/gocaptcha — PHP 行为验证码库设计文档

- **状态**：已批准
- **日期**：2026-06-18
- **包名**：`phgors/gocaptcha`
- **仓库**：https://github.com/phgors/gocaptcha
- **协议**：Apache-2.0
- **目标**：为 [go-captcha](https://github.com/wenlng/go-captcha) 生态提供 PHP 后端支持库，用 PHP（GD）原生实现验证码生成与校验，发布到 packagist，与现有 Vue/React/Angular/Svelte/Solid/UniApp 前端库对接。

---

## 1. 背景与目标

go-captcha 是一个 Go 行为验证码库，支持三种验证码类型：

| 类型 | 交互 | Go 包 |
|------|------|-------|
| 点选（Click） | 用户点击主图中指定的字符/图形 | `click` |
| 滑动（Slide） | 用户滑动/拖拽拼图块到缺口 | `slide` |
| 旋转（Rotate） | 用户旋转缩略图对齐主图角度 | `rotate` |

生态中已有 Go 主库、Go HTTP 服务（go-captcha-service）、各框架前端库，但缺少 PHP 原生实现。PHP 项目当前只能依赖外部 Go 服务或缺少验证码生成能力。

### 目标

- 用 PHP（GD 扩展）**原生、全量重写**三种验证码的图片生成与校验逻辑
- 内置默认素材，**开箱即用**，用户无需准备字体/图片即可运行
- 对外 API 风格为 **PHP 惯用风格**（命名构造 + 链式 setter + DTO）
- **纯无状态**，不耦合任何存储；调用方自行决定如何保存校验数据
- **框架无关**核心，Laravel/Symfony 用户均可直接使用
- 与现有前端库**协议兼容**（前端只消费 base64 图像，无字段耦合）

### 非目标（首版）

- 不内置 Laravel Service Provider / Symfony Bundle（后续可单独发包）
- 不集成 go-captcha-service（不做 HTTP 客户端模式）
- 不支持 Imagick 后端（首版仅 GD，未来可加）

---

## 2. 技术决策

| 决策项 | 选择 | 理由 |
|--------|------|------|
| PHP 最低版本 | 7.4+ | 兼容性最广，覆盖绝大多数现存 PHP 环境 |
| 图像扩展 | GD（`ext-gd`） | 几乎所有环境自带，零额外安装成本 |
| API 风格 | PHP 惯用（链式 Builder + 不可变 Options + DTO） | 对 PHP 用户自然，类似 Laravel 风格 |
| 素材策略 | 包内 `resources/` 内置默认 | 开箱即用体验 |
| 状态管理 | 纯无状态 | 库不耦合存储，最大灵活性 |
| 框架集成 | 框架无关核心 | 首版范围可控 |
| 架构模式 | 分层 + 共享 Base 层 | 镜像 Go 主库结构，便于移植验证 |
| 协议 | Apache-2.0 | 与 go-captcha 主库一致 |
| 命名空间根 | `Phgors\GoCaptcha\`（PSR-4 → `src/`） | 与 vendor 一致 |

---

## 3. 整体架构

### 3.1 包结构

```
phgors/gocaptcha/
├── src/
│   ├── Base/
│   │   ├── Size.php                 # 不可变尺寸值对象 (width, height)
│   │   ├── Point.php                # 不可变坐标点 (x, y)
│   │   ├── RangeVal.php             # 范围值对象 (min, max)
│   │   ├── Color.php                # RGB(A) 颜色值对象，带 hex 解析
│   │   ├── Canvas.php               # GD 画布封装：创建、合成、输出
│   │   ├── ImageEncoder.php         # JPEG/PNG → bytes/base64（带 data-uri 前缀）
│   │   ├── Rng.php                  # 可注入随机数源（默认 mt_rand，可注入固定种子用于测试）
│   │   └── Distortion.php           # 正弦扭曲、噪点圆、干扰线（共享视觉变形算法）
│   ├── Assets/
│   │   ├── AssetLoader.php          # 资源定位：优先用户路径，回退内置默认
│   │   ├── DefaultAssets.php        # 内置字体/背景/拼图的静态访问器
│   │   └── Font.php                 # 字体值对象 (path, size)
│   ├── Click/
│   │   ├── ClickCaptcha.php         # 入口类：generate() 返回 ClickCaptchaData
│   │   ├── ClickBuilder.php         # 链式配置 + 资源装配
│   │   ├── ClickOptions.php         # 不可变配置 DTO
│   │   ├── ClickResourceBag.php     # 持有 chars/fonts/backgrounds/shapes
│   │   ├── Dot.php                  # 校验点 (index, x, y, size)
│   │   ├── ClickCaptchaData.php     # 生成结果：masterImage, thumbImage, dots[]
│   │   └── ClickValidator.php       # 校验逻辑
│   ├── Slide/
│   │   ├── SlideCaptcha.php
│   │   ├── SlideBuilder.php
│   │   ├── SlideOptions.php
│   │   ├── GraphImage.php           # 拼图素材 (overlay, mask, shadow)
│   │   ├── MaskProcessor.php        # GD 像素级 alpha mask 合成
│   │   ├── Block.php                # 校验块 (x, y, width, height, angle)
│   │   ├── SlideCaptchaData.php     # masterImage, tileImage, block
│   │   └── SlideValidator.php
│   ├── Rotate/
│   │   ├── RotateCaptcha.php
│   │   ├── RotateBuilder.php
│   │   ├── RotateOptions.php
│   │   ├── RotateBlock.php          # 校验块 (angle)
│   │   ├── RotateCaptchaData.php    # masterImage, thumbImage, block
│   │   └── RotateValidator.php
│   ├── ImageData/
│   │   ├── JpegImage.php            # 主图（JPEG）：toBytes/toBase64/toBase64Data/saveToFile
│   │   └── PngImage.php             # 缩略图/拼图（PNG，含透明通道）
│   └── Exception/
│       ├── CaptchaException.php     # 接口 (extends \Throwable)
│       ├── GenerationException.php  # 生成阶段错误
│       ├── ResourceException.php    # 素材缺失/无效
│       └── ValidationException.php  # 校验参数错误
├── resources/
│   ├── fonts/                       # 内置开源字体（OFL 协议）
│   ├── backgrounds/                 # 内置主图背景（JPEG）
│   ├── tiles/                       # 内置拼图素材（overlay/mask/shadow PNG）
│   └── chars.php                    # 默认字符集（中文/字母数字）
├── tests/                           # PHPUnit 测试
├── composer.json
├── LICENSE
├── README.md
└── CHANGELOG.md
```

### 3.2 依赖关系

```
Click / Slide / Rotate   ──►  ImageData (JpegImage/PngImage)
        │                       ▲
        ├──►  Assets (Loader)   │
        └──►  Base (Canvas, Distortion, Rng, Color, Size, Point, RangeVal)
```

- 三种验证码互不依赖，各自只依赖 `Base/`、`Assets/`、`ImageData/`
- `Base/Canvas` 封装所有 GD 的 `imagecreatetruecolor`、`imagecopy`、`imagecopyresampled`、`imagedestroy`，GD 调用集中于此，便于错误处理与未来替换
- `ImageData/` 把"图像如何输出"与"图像如何生成"解耦：生成产物为 `JpegImage`/`PngImage` 值对象，调用方按需转 base64 或存盘

### 3.3 关键设计原则

1. **前端协议兼容**：`CaptchaData::toArray()`/`toJson()` 只暴露前端需要的字段（图像 base64）；答案字段（dots/block）通过独立 getter 取，避免误回前端。前端只消费两张 base64 图像，无字段耦合。
2. **GD 调用集中化**：所有 `image*()` 函数只在 `Base/Canvas`、`ImageData/`、`Slide/MaskProcessor` 内出现，业务层操作画布对象，便于测试 mock 与错误兜底。
3. **不可变配置**：`Builder` 链式构造后产出不可变 `Options`，`Captcha` 持有 `Options` 可被重复调用 `generate()`，避免意外副作用。

---

## 4. 点选验证码（Click）详细设计

### 4.1 数据模型

```php
class Dot {
    public int $index;   // 目标点序号
    public int $x;       // 主图坐标 X
    public int $y;       // 主图坐标 Y
    public int $size;    // 渲染尺寸（用于校验容差范围）
}
```

### 4.2 生成算法（移植 Go `click` 包）

1. `ClickBuilder` 装配配置 + 资源（字符集/字体/背景/图形），`make()` 产出不可变 `ClickCaptcha`
2. `generate()`：
   - 随机选一张背景图，建主图画布（默认 300×220）
   - 从 `rangeLen` 随机取总数 N，从 `rangeVerifyLen` 取目标数 M（M ≤ N）
   - 洗牌取 N 个字符，**碰撞回避**放置（采样位置 + 最小距离校验，重试上限防止死循环）
   - 每个字符用 `imagettftext` 按随机角度/字号/颜色渲染，可选阴影偏移
   - 标记前 M 个为校验目标 → `Dot[]`
   - 缩略图画布（默认 150×40，PNG）：按顺序渲染目标字符，叠 `Distortion` 扭曲/噪点圆/干扰线
   - 返回 `ClickCaptchaData{dots, masterImage:JpegImage, thumbImage:PngImage}`

### 4.3 文本 / 图形双模式

- `make()` 文本模式：用字体渲染字符
- `makeWithShape()` 图形模式：用 `imagecopy` 贴图代替字体渲染，由 `ShapeBag` 提供

### 4.4 配置项（对应 Go Options）

| 配置 | 默认值 | 说明 |
|------|--------|------|
| `imageSize` | 300×220 | 主图尺寸 |
| `thumbImageSize` | 150×40 | 缩略图尺寸 |
| `rangeLen` | {4,5} | 主图内容总数范围 |
| `rangeVerifyLen` | {2,4} | 校验目标数范围 |
| `rangeAnglePos` | 多个 | 随机角度范围 |
| `rangeSize` | — | 内容字号范围 |
| `rangeColors` | 多个 | 随机颜色 |
| `displayShadow` | false | 是否显示阴影 |
| `shadowColor` | — | 阴影颜色 |
| `thumbBgDistort` | DistortLevel2 | 缩略图背景扭曲等级 |

### 4.5 校验

```php
// 单点对单点（对标 Go click.Validate）
ClickValidator::checkPoint(int $srcX, int $srcY, Dot $dot, int $padding): bool

// 批量便捷方法：每个 dot 须被恰好一个用户点命中（顺序无关）
ClickValidator::validate(array $dots, array $userPoints, int $padding): bool
```

---

## 5. 滑动验证码（Slide）详细设计

### 5.1 数据模型

```php
class GraphImage {
    public $overlay;   // 拼图块贴图（PNG）
    public $mask;      // 形状蒙版（灰度 PNG）
    public $shadow;    // 阴影蒙版（PNG）
}

class Block {
    public int $x;       // 目标缺口 X
    public int $y;       // 目标缺口 Y
    public int $width;   // 拼图块宽
    public int $height;  // 拼图块高
    public int $angle;   // 角度
}
```

### 5.2 生成算法（移植 Go `slide` 包）

1. 选背景 + 选拼图素材（`GraphImage{overlay, mask, shadow}`）
2. 定位目标位置 (x,y)：
   - `make()` 基本模式：Y 固定贴底
   - `makeWithRegion()` 拖拽模式：Y 随机（受 `rangeDeadZoneDirections` 盲区约束）
3. **主图（JPEG）**：在缺口处绘 shadow 半透明阴影 → 用 mask 做像素级 alpha 抠出缺口
4. **拼图块（PNG，带透明通道）**：overlay 叠 mask 生成可拖动拼图块
5. `block = {x, y, width, height, angle}` 为校验答案

### 5.3 核心难点：GD mask 合成

GD 无原生 alpha mask 操作。新建 `Slide/MaskProcessor`：
- 拼图块尺寸小（~60×60），按像素遍历读 mask 灰度值 → 写入目标 alpha（`imagecolorallocatealpha` + `imagesetpixel`）
- 性能可接受（每帧约几千次像素操作），集中在 `MaskProcessor` 内、可独立单测
- 缺口抠图、拼图块透明化、阴影半透明合成统一复用此处理器

### 5.4 校验

```php
// 对标 Go slide.Validate(srcX, srcY, X, Y, padding)
SlideValidator::validate(Block $block, int $userX, int $userY, int $padding): bool
```

---

## 6. 旋转验证码（Rotate）详细设计

### 6.1 数据模型

```php
class RotateBlock {
    public int $angle;   // 目标旋转角度（0-360）
}
```

### 6.2 生成算法（移植 Go `rotate` 包）

1. 选背景，裁剪/缩放为正方形（默认 220×220）
2. 从 `rangeAnglePos` 随机取目标角度
3. **主图**：背景按目标角度旋转（`imagerotate`，透明边填充背景色或扩展）→ 输出
4. **缩略图（PNG）**：从中心裁圆形区域 + 圆形 alpha 蒙版 + 透明度 → 圆形可旋转缩略图
5. `block = {angle}` 为校验答案

### 6.3 校验（角度环形差）

```php
// 对标 Go rotate.Validate(srcAngle, angle, padding)
// 注意 0°/360° 邻接，用环形差判定
RotateValidator::validate(int $angle, int $userAngle, int $padding): bool
// 内部：diff = min(abs(userAngle - angle), 360 - abs(userAngle - angle)) <= padding
```

### 6.4 统一图像格式

三种验证码：**主图统一 JPEG**、**缩略图/拼图统一 PNG**。前端按 base64 直接消费，无格式差异需要处理。

---

## 7. 素材资源（Assets）设计

### 7.1 加载策略

- `AssetLoader::font($name)`：优先用户传入路径，回退 `resources/fonts/`
- `AssetLoader::backgrounds()`：回退 `resources/backgrounds/`
- `AssetLoader::tiles()`：回退 `resources/tiles/`
- `DefaultAssets`：提供静态访问器，封装内置素材

### 7.2 内置默认素材

- **字体**：1 个开源中文字体（SIL OFL 协议，如思源黑体子集）
- **背景图**：~10 张 JPEG（自然/抽象图，来源合规）
- **拼图素材**：~10 套 PNG（overlay/mask/shadow 三件套）
- **字符集**：`resources/chars.php` 返回中文字符集与字母数字字符集

### 7.3 许可与体量

- 仅打包兼容许可证（OFL / CC0 / Apache）素材，README 注明来源与许可
- 默认素材压缩后控制在数 MB 内，避免包体过大

---

## 8. 校验与无状态设计

- 三种 `Validator` 均为**无状态纯函数式**，不接触任何存储
- `generate()` 返回的 `CaptchaData` 同时含图像与答案数据；调用方自行决定：
  - 存 Session / Cache / Redis，或
  - 用密钥签名成 token 返回前端（库不内置此逻辑，留给用户）
- `CaptchaData::toArray()`/`toJson()` 仅暴露前端字段（图像 base64），答案字段通过 `getDots()`/`getBlock()` 独立 getter 取，避免误回前端

---

## 9. 错误处理

### 9.1 异常层级

```
CaptchaException (接口, extends \Throwable)
├── ResourceException      # 素材缺失/字体无效/图片解码失败
├── GenerationException    # 配置非法（如字符数 < rangeLen.Max）、画布创建失败、放置碰撞超限
└── ValidationException    # 校验入参缺失/类型不符
```

### 9.2 错误处理规则

- `Base/Canvas` 内所有 GD 调用 `@` 抑制 + 检查返回值 → 抛 `ResourceException` / `GenerationException`
- 错误信息使用**中文友好描述** + 附带上下文（如缺失的具体素材名、非法配置项）
- 校验阶段入参缺失/类型不符抛 `ValidationException`

---

## 10. 测试策略

### 10.1 测试框架

- **PHPUnit 9.6**（兼容 PHP 7.4+）

### 10.2 测试覆盖

- **单测**：
  - `Base/`：值对象、颜色 hex 解析、`Rng` 固定种子、`Distortion` 数学正确性
  - `Slide/MaskProcessor`：mask → alpha 像素级正确性
  - 各 `Validator`：边界值、环形角度、批量匹配真值表
- **集成测**：
  - 每种验证码 `generate()` 产出图像（GD 回读校验尺寸/非空/格式）
  - 数据结构字段完整
- **基线样本**：fixtures 存固定种子输出，比对尺寸与相似度（不做逐像素比对，因 GD 版本差异）

### 10.3 可复现性

- `Rng` 可注入固定种子，保证生成结果可复现，测试断言稳定

### 10.4 CI

- **GitHub Actions** 矩阵：PHP 7.4 / 8.0 / 8.1 / 8.2 / 8.3（ubuntu-latest + `ext-gd`）
- 流程：`composer install` → `composer test`

---

## 11. 打包与发布

### 11.1 composer.json

```json
{
    "name": "phgors/gocaptcha",
    "description": "PHP 行为验证码库（点选/滑动/旋转），go-captcha 生态 PHP 后端实现",
    "type": "library",
    "license": "Apache-2.0",
    "keywords": ["captcha", "go-captcha", "click-captcha", "slide-captcha", "rotate-captcha", "gd"],
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

### 11.2 仓库与命名空间

- 仓库：`E:\workspace\php\gocaptcha`，远程 `phgors/gocaptcha`
- 根命名空间：`Phgors\GoCaptcha\`（PSR-4 → `src/`）

### 11.3 文档

- `README.md`：含三种验证码完整使用示例（生成 + 校验）、安装说明、配置项表、素材替换说明
- `CHANGELOG.md`：版本变更记录

### 11.4 发布流程

1. 完成 implementation plan，代码与测试通过
2. `git tag v1.0.0`
3. 推送到 `phgors/gocaptcha`
4. 在 packagist.org 提交仓库 URL（用户手动操作）
5. 设置 packagist 自动更新 webhook（可选）

### 11.5 版本策略

- semver，首版 `v1.0.0`
- 破坏性 API 变更需主版本号 + 文档迁移指南

---

## 12. 使用示例（预期 API）

```php
use Phgors\GoCaptcha\Click\ClickBuilder;
use Phgors\GoCaptcha\Assets\DefaultAssets;
use Phgors\GoCaptcha\Click\ClickValidator;

// 点选验证码
$captcha = ClickBuilder::make()
    ->setBackgrounds(DefaultAssets::backgrounds())
    ->setFonts(DefaultAssets::fonts())
    ->setChars(DefaultAssets::chineseChars())
    ->build();

$data = $captcha->generate();

// 返回前端
$response = [
    'masterImage' => $data->getMasterImage()->toBase64(),   // data:image/jpeg;base64,...
    'thumbImage'  => $data->getThumbImage()->toBase64(),    // data:image/png;base64,...
];
// $data->getDots() 自行存 Session/Cache 作为答案

// 校验（下次请求）
$ok = ClickValidator::validate($storedDots, $userClickPoints, $padding = 10);
```

---

## 13. 后续演进（非首版）

- Laravel Service Provider / Facade（独立包）
- Symfony Bundle（独立包）
- Imagick 后端（双后端抽象）
- go-captcha-service HTTP 客户端模式
- 额外内置素材包（独立 packagist 包，按需安装）
