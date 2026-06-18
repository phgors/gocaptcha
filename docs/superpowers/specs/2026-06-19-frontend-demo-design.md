# 前端演示（go-captcha-jslib 对接）设计

> 日期：2026-06-19
> 状态：已与用户确认设计，待制定实现计划
> 关联库：[phgors/gocaptcha](https://github.com/phgors/gocaptcha)（本仓库）× [go-captcha-jslib](https://www.npmjs.com/package/go-captcha-jslib) v1.0.9

## 1. 目标

为本仓库（PHP 行为验证码库 `phgors/gocaptcha`）提供一个**端到端**的前端演示，演示如何用纯 JavaScript 库 `go-captcha-jslib` 对接 PHP 后端，完整展示四种验证码模式（点选 / 滑动 / 区域拖拽 / 旋转）的「生成 → 用户交互 → 校验」全流程。

### 成功标准

- 一条命令启动（`php -S`），浏览器访问即可看到四种验证码全部可交互、可校验。
- 每种模式：能加载后端生成的图像、能采集用户交互、能调用后端校验并显示成功/失败。
- 答案绝不回传前端（仅存 PHP Session）。
- 前端零构建、零 npm 依赖（CDN 引入），后端零外部框架（复用本仓库）。

### 非目标（YAGNI）

- 不做生产级安全加固（无频控、无签名 token、无 CSRF 防护）——demo 性质。
- 不做持久化、不接数据库、不做用户体系。
- 不做移动端深度适配，仅做基本窄屏布局。
- 不引入前端构建工具（Vite/webpack）与 npm 包管理。
- 不引入 PHP 框架（Laravel/Yii 等），用原生 PHP + 内置 server。
- 不修改 `phgors/gocaptcha` 库本身的源码（仅通过其公开 API 使用）。

## 2. 总体架构

```
┌─────────────────────────────┐         ┌───────────────────────────────┐
│  浏览器（单页）              │   HTTP  │  PHP 内置 server              │
│                             │ ◀─────▶ │                                │
│  go-captcha-jslib (CDN)     │         │  server.php（路由入口）        │
│  ├─ Click 组件              │  fetch  │   ├─ /api/*  → 生成/校验        │
│  ├─ Slide 组件              │ ──────▶ │   └─ 其余    → return false     │
│  ├─ SlideRegion 组件        │         │                （服务静态文件） │
│  └─ Rotate 组件             │         │                                │
│                             │         │  复用 vendor/autoload.php       │
│  app.js（API 调用/事件）     │         │  → phgors/gocaptcha（本仓库）   │
└─────────────────────────────┘         │  答案存 $_SESSION               │
                                        └───────────────────────────────┘
```

**核心数据流**：前端 fetch 生成接口 → 后端 `Builder→generate()` 取图像 + 答案 → 组装成 `go-captcha-jslib` 字段格式回前端、答案存 Session → 用户交互 → confirm 事件触发 fetch 校验接口 → 后端从 Session 取答案、用 `Validator` 校验 → 返回 `{ok}`。

## 3. 项目结构

放在仓库 `examples/frontend-demo/` 下（PHP 生态常见约定，独立子目录，不污染库主代码）。

```
examples/frontend-demo/
├── public/                # 文档根（php -S -t 指向它）
│   ├── index.html         # 单页：标题 + Tab 栏 + 四个面板
│   ├── app.js             # 前端逻辑：配置表驱动四种模式
│   └── style.css          # 极简样式：布局、Tab、状态条
├── server.php             # 路由入口：/api/* 处理，其余 return false
└── README.md              # 运行说明
```

### 启动命令

```bash
php -S localhost:8000 -t examples/frontend-demo/public examples/frontend-demo/server.php
```

浏览器访问 `http://localhost:8000`。

`server.php` 顶部 `require __DIR__ . '/../../vendor/autoload.php'` 复用仓库根目录已安装的本库（PSR-4：`Phgors\GoCaptcha\` → `src/`）。

> 前置条件：仓库根目录已 `composer install`（`vendor/` 存在）；PHP 启用 `ext-gd`（建议 FreeType）。

## 4. API 设计（server.php）

### 路由

所有 `/api/*` 由 `server.php` 处理并 `return`（终止）；其余请求 `return false` 交 PHP 内置 server 服务 `public/` 下静态文件。

| 方法 | 路径 | 作用 |
|------|------|------|
| GET | `/api/click` | 生成点选验证码 |
| POST | `/api/click/verify` | 校验点选 |
| GET | `/api/slide` | 生成滑动验证码 |
| POST | `/api/slide/verify` | 校验滑动 |
| GET | `/api/slide-region` | 生成区域拖拽验证码 |
| POST | `/api/slide-region/verify` | 校验区域拖拽 |
| GET | `/api/rotate` | 生成旋转验证码 |
| POST | `/api/rotate/verify` | 校验旋转 |

- 统一 `session_start()`；`Content-Type: application/json; charset=utf-8`。
- 生成类接口：调用对应 Builder（缓存为静态变量，可重复 `generate()`），取 `CaptchaData`，组装前端字段返回，答案存 `$_SESSION`。
- 校验类接口：从 `$_SESSION` 取答案，读取 `php://input` 的 JSON，调用对应 `Validator::validate()`，返回 `{ok: bool}`。
- 异常 `try/catch`：捕获后返回 `{ok:false, error:'<msg>'}` 与 HTTP 500。

### Builder 构造（server.php 内）

- **Click**：`ClickBuilder::make()->setBackgrounds(DefaultAssets::backgrounds())->setFonts(DefaultAssets::fonts())->setChars(DefaultAssets::chineseChars())->build()`
- **Slide**：`SlideBuilder::make()->setBackgrounds(...)->setGraphs($graphs)->build()`，其中 `$graphs` 由 `DefaultAssets::tileSets()` 经 `GraphImage` 映射。
- **SlideRegion**：复用上面的 `SlideBuilder`，改调 `->buildRegion()`。
- **Rotate**：`RotateBuilder::make()->setBackgrounds(...)->build()`。

缓存 **3 个** Builder 实例（`Click`、`Slide`、`Rotate`）为 `static` 变量——其中 `Slide` Builder 同时服务 `/api/slide`（`build()`）与 `/api/slide-region`（`buildRegion()`）。构造一次，反复 `generate()`，避免每次请求重读素材。

## 5. 数据映射（后端 → 前端字段）

> 关键约束：后端 `CaptchaData::toArray()` 仅返回图像 base64（`masterImage` + `thumbImage`/`tileImage`），不含 Slide/Rotate 渲染所需的元数据。demo 的 API 层必须访问 `getBlock()`/`getDots()` 自行组装 `go-captcha-jslib` 的 `setData()` 字段，并把答案单独存 Session。

### ① Click（点选）

后端字段已够用，仅做重命名。

| go-captcha-jslib `setData` | 来源 |
|----|----|
| `image` | `$data->getMasterImage()->toBase64()` |
| `thumb` | `$data->getThumbImage()->toBase64()` |

- **Session 存**：`array_map(fn($d)=>$d->toArray(), $data->getDots())` → `[{index,x,y,size}, ...]`
- **校验入参**（POST JSON）：`{points: [{x,y}, ...]}`
- **校验**：`ClickValidator::validate($dots, $points, 10)`
- **前端 config**：`{width:300, height:220, thumbWidth:150, thumbHeight:40}`

### ② Slide（滑动）

后端 `Block{ x, y, width, height, angle }`：`x,y` 是**答案**（master 上空洞位置，位于右侧）。前端需要拼图**初始绘制位置**。

| go-captcha-jslib `setData` | 来源 |
|----|----|
| `image` | `masterImage` |
| `thumb` | `tileImage` |
| `thumbX` | 左侧小起始值（拼图初始绘制 x，与答案 x 无关；普通模式拼图从左侧水平滑入） |
| `thumbY` | `block.y`（普通模式 y 不变，拼图与空洞同行） |
| `thumbWidth` | `block.width` |
| `thumbHeight` | `block.height` |

- **Session 存**：`$block->toArray()`（答案 `x,y`）
- **校验入参**：`{x, y}`（confirm 回传的拖动终点）
- **校验**：`SlideValidator::validate($block, $x, $y, 5)`
- **前端 config**：`{width:300, height:220}`

### ③ SlideRegion（区域拖拽）

后端 `buildRegion()`：`block.y` 在全高范围随机（区别于普通模式贴底）。字段结构与 Slide 一致，但拼图初始位置放左上角，远离右侧目标。

| go-captcha-jslib `setData` | 来源 |
|----|----|
| `image` | `masterImage` |
| `thumb` | `tileImage` |
| `thumbX` | 左上小起始值 |
| `thumbY` | 左上小起始值（区域模式：初始置于角落，与答案位置无关） |
| `thumbWidth` | `block.width` |
| `thumbHeight` | `block.height` |

- **Session 存** / **校验入参** / **校验**：同 Slide
- **前端 config**：`{width:300, height:220}`

### ④ Rotate（旋转）

后端 `RotateBlock{ angle }`（答案 = master 被旋转的角度）；默认 `imageSquareSize=220`、`thumbSquareSize=150`。

| go-captcha-jslib `setData` | 来源 |
|----|----|
| `image` | `masterImage` |
| `thumb` | `thumbImage` |
| `angle` | `0`（thumb 初始正放，用户旋转去对齐 master） |
| `thumbSize` | `150`（= `thumbSquareSize`） |

- **Session 存**：答案 `angle`
- **校验入参**：`{angle}`（confirm 回传的用户累计旋转角）
- **校验**：`RotateValidator::validate($answerAngle, $userAngle, 8)`（已处理 `%360` 与 `>180` 折返）
- **前端 config**：`{width:220, height:220}`

### 起始位置取值说明

Slide 的 `thumbX`、SlideRegion 的 `thumbX/thumbY` 取「小的左侧/角落起始值」（具体数值在实现阶段微调，如 5）。要点：该值是**渲染初始位置**，非答案，且不与右侧目标空洞重叠。

## 6. 前端设计

### CDN 引入（index.html `<head>`）

```html
<link rel="stylesheet" href="https://unpkg.com/go-captcha-jslib@1.0.9/dist/gocaptcha.global.css">
<script src="https://unpkg.com/go-captcha-jslib@1.0.9/dist/gocaptcha.global.js"></script>
```

使用全局 `window.GoCaptcha`：`.Click` / `.Slide` / `.SlideRegion` / `.Rotate`。

### 页面结构（index.html）

- 标题区：项目名 + 一句说明（"phgors/gocaptcha 前端演示 — go-captcha-jslib"）。
- Tab 栏：点选 / 滑动 / 区域拖拽 / 旋转。
- 内容区：四个面板（切换显示，非销毁）。
- 每个面板：
  - 状态提示条（含刷新按钮）；
  - 验证码容器 `<div id="captcha-{type}">`（组件 mount 目标）；
  - 操作引导文案（点击图中文字 / 拖动拼图到缺口 / 旋转缩略图对齐）。

### app.js：一张配置表驱动四种模式

```js
const MODES = {
  click:          { ctor: GoCaptcha.Click,       api:'/api/click',        cfg:{width:300,height:220,thumbWidth:150,thumbHeight:40} },
  slide:          { ctor: GoCaptcha.Slide,       api:'/api/slide',        cfg:{width:300,height:220} },
  'slide-region': { ctor: GoCaptcha.SlideRegion, api:'/api/slide-region', cfg:{width:300,height:220} },
  rotate:         { ctor: GoCaptcha.Rotate,      api:'/api/rotate',       cfg:{width:220,height:220} },
};
```

- `instances[type]` 懒创建：首次切到某 Tab 才 `new ctor(cfg)` → `mount(el)` → `setEvents(...)` → `load(type)`。
- 切走不销毁，切回保留状态；提供刷新按钮重新 `load`。

### 交互流程（通用）

```
进入 Tab → init: new + mount + setEvents → load: GET /api/{type} → setData
                                                              │
                                          [用户交互: 点击/拖动/旋转]
                                                              │
                                       组件 confirm(payload, reset) 事件
                                                              │
                                            POST /api/{type}/verify {payload}
                                                              │
                                              ┌───────────────┴───────────────┐
                                              ▼                               ▼
                                          ok: true                        ok: false
                                              │                               │
                                       状态条=success              状态条=error + reset() 重来
```

### 各模式 confirm 事件 → 校验入参

| 模式 | confirm 回调签名 | POST body |
|------|------------------|-----------|
| Click | `confirm(dots, reset)` | `{points: dots.map(d=>({x:d.x,y:d.y}))}` |
| Slide | `confirm(point, reset)` | `{x: point.x, y: point.y}` |
| SlideRegion | `confirm(point, reset)` | `{x: point.x, y: point.y}` |
| Rotate | `confirm(angle, reset)` | `{angle}` |

- `refresh` 事件 → 重新 `load(type)`。
- 校验**失败** → 调 `reset()` 让用户重试；**成功** → 状态条置绿。
- 实现阶段在 confirm 回调内先 `console.log(payload)` 确认各模式回传结构（见第 7 节待验证点）。

### 状态与错误处理

- 状态条四态：`idle`（引导文）/ `loading`（灰）/ `success`（绿）/ `error`（红，附原因）。
- `fetch` 抛错或 HTTP 非 2xx → `error` 态："请求失败，请点刷新重试"，并 `console.error` 详情。
- 后端异常返回 `{ok:false}` 或非 2xx，前端按失败处理。
- load/verify 期间状态条显 `loading`；组件不被禁用（避免阻塞）。

### 样式（style.css）

极简居中卡片布局；Tab 横向排列；状态条用背景色区分四态；窄屏下面板居中、最大宽度约 560px。

## 7. 待实现阶段验证的语义假设

设计基于以下假设，实现时用 `console.log` 打印实际回传值确认；若与假设不符则在校验换算处调整，不影响整体架构：

1. **Slide `confirm(point)` 的 `point`**：假设为拼图**最终绝对坐标**（与答案 `block.x/y` 直接比较）。若实测为"相对 `thumbX` 的拖动偏移"，则校验换算为 `userX = thumbX + point.x`、`userY = thumbY + point.y`。
2. **SlideRegion `confirm` 回调签名**：go-captcha-jslib README 的示例写 `confirm(dots, reset)`、TS 接口写 `confirm(point, reset)`，存在矛盾。本设计按**单点 `point`** 处理（因后端 `buildRegion()` 只产单个拼图，用户只拖一块）。若实测回传的是数组 `dots`，则取其元素映射为 `{x,y}`，或按多块语义调整（后端目前只支持单块校验，多块超出本 demo 范围）。
3. **Rotate `confirm(angle)` 的 `angle`**：假设为用户累计旋转角（0–360），后端 `RotateValidator` 已处理归一化。
4. **Click `confirm(dots)` 的元素结构**：假设为含 `x`,`y` 的对象；若为 `[x,y]` 数组则映射逻辑兼容（后端 `ClickValidator::pointCoords` 已兼容两种）。

## 8. 运行与验证清单

实现完成后按下列步骤验证：

1. 仓库根 `composer install`（确保 `vendor/` 存在）。
2. `php -S localhost:8000 -t examples/frontend-demo/public examples/frontend-demo/server.php`。
3. 浏览器打开 `http://localhost:8000`，依次切到四个 Tab：
   - 图像正常加载（主图 + 缩略图/拼图）。
   - 完成「正确」交互 → 显示成功。
   - 故意「错误」交互 → 显示失败并 reset。
   - 点刷新 → 重新加载新图。
4. 检查 Network：生成接口返回前端字段、**不含**答案；校验接口返回 `{ok}`。
5. 确认 Session 中存有答案、未泄露给前端。

## 9. 风险与对策

| 风险 | 对策 |
|------|------|
| Slide/SlideRegion/Rotate confirm 语义与假设不符 | 实现期 console.log 验证，必要时换算或调整映射（第 7 节） |
| 后端 `toArray()` 未暴露元数据 | demo API 层访问 `getBlock()`/`getDots()` 自行组装（不改库源码） |
| CDN 不可达 | README 注明可换 jsdelivr；不影响后端逻辑 |
| PHP Session 跨请求丢失 | 确保 `session_start()` 在路由最前；cookie 同源同端口 |
| Builder 重复构造开销 | 用 `static` 缓存 Builder，反复 `generate()` |
