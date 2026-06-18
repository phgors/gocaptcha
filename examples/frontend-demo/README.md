# phgors/gocaptcha 前端演示

用 [go-captcha-jslib](https://www.npmjs.com/package/go-captcha-jslib)（CDN 引入，v1.0.9）对接本仓库 PHP 后端，演示 **点选 / 滑动 / 区域拖拽 / 旋转** 四种验证码的完整「生成 → 交互 → 校验」流程。

## 运行

前置：仓库根目录已 `composer install`（`vendor/` 存在）；PHP ≥ 7.4 启用 `ext-gd`（建议 FreeType）。

```bash
# 在仓库根目录执行
php -S localhost:8000 -t examples/frontend-demo/public examples/frontend-demo/server.php
```

浏览器打开 <http://localhost:8000>，切换顶部 Tab 体验四种验证码。

## 数据流

```
进入 Tab → GET /api/{type} → setData(图像 + 元数据) → 用户交互
        → confirm → POST /api/{type}/verify → {ok} → 成功 / 失败(reset)
```

- 生成接口返回 go-captcha-jslib 渲染所需字段（图像 + 拼图初始位置/尺寸/角度），**不含答案**。
- 答案只存在 PHP Session，校验时从 Session 取出比对。

## API 一览

| 方法 | 路径 | 作用 |
|------|------|------|
| GET | `/api/click` | 生成点选 → `{image, thumb}` |
| POST | `/api/click/verify` | 校验 → `{ok}`，body `{points:[{x,y},...]}` |
| GET | `/api/slide` | 生成滑动 → `{image, thumb, thumbX, thumbY, thumbWidth, thumbHeight}` |
| POST | `/api/slide/verify` | 校验 → `{ok}`，body `{x, y}` |
| GET | `/api/slide-region` | 生成区域拖拽 → 同 slide 字段 |
| POST | `/api/slide-region/verify` | 校验 → `{ok}`，body `{x, y}` |
| GET | `/api/rotate` | 生成旋转 → `{image, thumb, angle, thumbSize}` |
| POST | `/api/rotate/verify` | 校验 → `{ok}`，body `{angle}` |

## 结构

| 文件 | 作用 |
|------|------|
| `server.php` | PHP 路由：8 个 `/api/*` 端点（4 生成 + 4 校验），缓存 Builder，答案存 Session |
| `assemblers.php` | 把 `CaptchaData` 组装成 go-captcha-jslib `setData()` 字段（纯函数） |
| `public/index.html` | 单页：CDN 引入、Tab、四个面板 |
| `public/app.js` | 配置表驱动的四种模式交互逻辑（懒加载/事件/校验） |
| `public/style.css` | 极简样式 |
| `tests/` | assemblers 的 PHPUnit 测试 |

## 跑测试

```bash
vendor/bin/phpunit --bootstrap examples/frontend-demo/tests/bootstrap.php examples/frontend-demo/tests/
```

## 浏览器验证清单

逐项确认（建议打开浏览器 DevTools 的 Console 与 Network）：

1. **点选**：进入「点选」Tab → 图像自动加载 → 按缩略图提示点选图中文字 → 点组件内确认。
   - 正确点选 → 状态条变绿「验证通过」。
   - 故意点错 → 变红「验证失败」并 reset。
   - Console 打印 `[click] confirm payload:`，元素应为含 `{x, y}` 的对象数组。
2. **滑动**：拖动滑块把拼图对准缺口 → 确认。对准则成功；Console 打印 `[slide] confirm payload:`。
3. **区域拖拽**：从左上角拖动拼图到缺口 → 确认。Console 打印 `[slide-region] confirm payload:`。
4. **旋转**：旋转缩略图与主图对齐 → 确认。对齐则成功；Console 打印 `[rotate] confirm payload:`（数字角度）。
5. 每个面板的「刷新」按钮 → 重新加载新图。

> **语义说明**：若滑动/区域拖拽「对得很准却总是失败」，说明该组件 `confirm` 回传的是相对 `thumbX` 的偏移而非绝对坐标。此时需在 `public/app.js` 对应 `toBody` 里把 `point.x/y` 加上 `__meta.thumbX/thumbY`（`__meta` 已在 `load()` 中缓存）。
