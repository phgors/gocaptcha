# 内置字体

本目录下随库分发的字体：

- `NotoSansSC-Regular.otf` — 静态区域子集（简体中文）

## 来源与授权

- 名称：Noto Sans SC（思源黑体的 Google 版本）
- 原始项目：Google（https://fonts.google.com/noto/specimen/Noto+Sans+SC ）
- 协议：SIL Open Font License 1.1（见同目录 `LICENSE-OFL.txt`）

库本身在 Apache-2.0 协议下分发，但内置字体独立遵循 OFL-1.1。
依据 OFL 条款，字体的版权声明与本许可证全文随字体一同再分发。

## 替换字体

把任意 TrueType（`.ttf`）或 OpenType（`.otf`）字体放入本目录即可被自动加载，
或通过 `ClickBuilder::setFonts()` 传入自定义 `Font` 列表覆盖默认字体。
