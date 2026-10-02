/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { describe, expect, it } from 'vitest';
import { lang, use } from './i18n';
import { FALLBACK } from './langs';

/**
 * 语言真值是**模块级信号**（`i18n.ts` 的 `LANG`），而 Angular 的 vitest builder 默认
 * `isolate: false` ⇒ 同一 worker 内的测试文件共用模块注册表，上一个文件留下的语言会
 * 留给下一个文件。`src/test-setup.ts` 负责在每个测试文件开始时复位。
 *
 * ⚠ 这条钉子是**概率性**的：只有本文件恰好排在某个「留了非兜底语言」的文件之后才会红
 * （实测：把复位值改成 `'ar'` ⇒ 本条红，收到 `'ar'`；去掉复位 ⇒ 收到上一个文件的 `'zh'`）。
 * 本文件排在 worker 首位时会平凡通过 —— 它验的是「复位存在且值对」，不是整套调度。
 */
describe('语言真值不跨测试文件泄漏', () => {
  it('文件开始：语言是兜底码', () => {
    expect(lang()).toBe(FALLBACK);
  });

  /** 故意留一个泄漏源：下一个文件若没有复位就会收到 `ar` */
  it('本文件把语言留成非兜底值', () => {
    use('ar');
    expect(lang()).toBe('ar');
  });
});
