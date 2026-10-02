/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { beforeEach } from 'vitest';
import { use } from './app/core/i18n/i18n';
import { FALLBACK } from './app/core/i18n/langs';

/**
 * 全树测试前置：每条用例开始前把语言偏好复位到兜底 `zh`。
 *
 * **为什么必须有（不是洁癖）**：本树 test 目标走 `@angular/build:unit-test`，
 * 它的 vitest 默认 **`isolate: false`**（见 `node_modules/@angular/build/src/builders/
 * unit-test/runners/vitest/plugins.js` 的 `projectDefaults`，注释写着 "align with the
 * Karma/Jasmine experience"）⇒ **同一 worker 里的 spec 文件共用模块注册表**，
 * 而当前语言是模块级信号（`i18n.ts` 的 `LANG`）。一个文件把语言切走又不收尾，
 * 同 worker 里**排在它后面**的文件中所有「断言中文」的用例就会读到外语。
 *
 * 实测（未改动的工作区连跑两次，挂在两个**不同**文件上）：
 *   - `deposit.spec.ts` 拿到 `'10.00 ~ No limit'`（en 泄漏，来自 `money-i18n.spec.ts`）
 *   - `wallet.spec.ts` 拿到 `'Buy game coins'` / `'ゲームコインを購入'`（en / ja 泄漏）
 * 失败落在哪个文件取决于 worker 调度 ⇒ **读数不可复现**，「只许增不许减」那道门形同虚设。
 *
 * 抽取把这层依赖放大：`limit()` / `exLabel()` 这类方法以前返回硬编码中文（与语言无关），
 * B1 起返回 `t(...)`；本树 `pages/*.spec.ts` 里有 **787 行含中文的断言**，
 * 以后每批抽取都会把其中一部分拉进这条依赖里 ⇒ 复位只能落在**全树**这一层，
 * 靠各文件自觉写 `beforeEach` 是拦不住的（写漏一个文件就随机红一次）。
 *
 * ⚠ 复位的是**偏好与信号**，不是断言：要在外语下取值的用例自己 `await use('ja')` 即可，
 * 本钩子注册在测试文件的钩子之前（vitest 按注册顺序跑 `beforeEach`）⇒
 * 用例自己种 `localStorage` / 切语言都仍然生效（`session.spec.ts`、`i18n.spec.ts` 都走这条路）。
 */
beforeEach(() => {
  try {
    localStorage.clear();
  } catch {
    /* 无 localStorage 的环境（非 jsdom）不影响本钩子的主要目的 */
  }
  // zh 表首屏就绪，`ensure('zh')` 同步 resolve ⇒ 不需要 await
  void use(FALLBACK);
});
