/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { FALLBACK } from './app/core/i18n/langs';
import { use } from './app/core/i18n/i18n';

/**
 * 每个**测试文件**开始前把「当前语言」复位到兜底码。
 *
 * 为什么需要：语言真值是**模块级信号**（`i18n.ts` 的 `LANG`），而 Angular 的 vitest builder
 * 默认 `isolate: false`（`@angular/build/.../unit-test/runners/vitest/plugins.js:128`，注释
 * "align with the Karma/Jasmine experience"）⇒ 同一 worker 内的测试文件**共用模块注册表**，
 * 上一条用例留下的语言会留给同 worker 的下一个文件。谁中招取决于 worker 调度 ⇒ 偶发。
 *
 * 为什么复位在**模块顶层**（每个测试文件一次）而不是 `beforeEach`：
 * 顶层落在测试文件自己的模块求值**之前**，于是「文件里显式定的语言」（`upload.spec.ts:10`
 * 那种模块级 `use('zh')`、以及各文件 `beforeEach` 里的 `use()`）全部照旧生效；`beforeEach`
 * 版会**盖掉**那条模块级 pin —— 实测 `upload.spec.ts:71`、`:164` 两条既有断言当场变红。
 * 跨文件泄漏是调度相关的、文件内泄漏（同文件用例之间）是固定顺序的 ⇒ 复位在文件边界即可。
 */
use(FALLBACK);
