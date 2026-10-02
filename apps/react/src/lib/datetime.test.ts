/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import assert from 'node:assert/strict';
import test from 'node:test';
import { dt } from './datetime.ts';

/** 期望值一律由本机时区现算，避免把 +08:00 写死进断言（换台机器跑照样成立） */
const local = (d: Date) => d.toLocaleString();

test('dt：两种线格式各自的时区语义', () => {
  // ① ISO8601 带 Z ＝ UTC 瞬间 ⇒ 必须换算到本机时区渲染
  assert.equal(dt('2026-10-05T02:00:00.000000Z'), local(new Date(Date.UTC(2026, 9, 5, 2, 0, 0))));

  // ② "Y-m-d H:i:s" 墙钟串（无时区标记）⇒ 按**本地时间**解释，不搬家
  assert.equal(dt('2026-10-01 10:00:00'), local(new Date(2026, 9, 1, 10, 0, 0)));

  // ③ 这两种写法指向同一串数字但**不是**同一瞬间（非 UTC 机器上）。
  //    这就是「直接把 ISO 串贴到 DOM 上会差 8 小时」的可执行判据。
  if (new Date().getTimezoneOffset() !== 0) {
    assert.notEqual(dt('2026-10-01 10:00:00'), dt('2026-10-01T10:00:00.000000Z'));
  }
});

/**
 * ⚠ 上面 ③ 的腿**在 CI 上是装饰的**：`.github/workflows/ci.yml` 全文没有 `TZ`
 * ⇒ GitHub runner 是 UTC ⇒ `getTimezoneOffset() === 0` ⇒ 那个分支**从不进入**。
 * 门本身开得对（UTC 上两串真的相等，硬断言就是假红），但它的牙只在非 UTC 机器上长着。
 *
 * 这条腿**不依赖宿主时区**，钉的是**实现形态**：`dt()` 不许用本地取法做**手工时区算术**
 * （`parsed.getHours() + 8`、`setHours(...)` 那一族）—— 归一只能交给 `Date` 解析与
 * `toLocaleString`。形态错了在 UTC 机器上看不出**症状**，但它是错的，而这条判据在
 * 任何时区都能红。
 *
 * 配方与本仓既有那条同源（`vi.spyOn(Date.prototype, 本地取法)` 断言 0 次），
 * 只是本树**没有 vitest**（`node --test`）⇒ 手写替换 + 计数。
 * ⚠ 计数必须在**还原之前**读：先还原再读就恒为 0。
 */
const LOCAL_GETTERS = [
  'getFullYear',
  'getMonth',
  'getDate',
  'getDay',
  'getHours',
  'getMinutes',
  'getSeconds',
  'getMilliseconds',
  'getTimezoneOffset',
] as const;

test('dt：归一不走本地取法（UTC 机器上也有牙，不依赖宿主时区）', () => {
  const proto = Date.prototype as unknown as Record<string, (...args: never[]) => unknown>;
  const counts: Record<string, number> = {};
  const saved = new Map<string, (...args: never[]) => unknown>();
  for (const name of LOCAL_GETTERS) {
    saved.set(name, proto[name]!);
    proto[name] = function patched(this: Date, ...args: never[]): unknown {
      counts[name] = (counts[name] ?? 0) + 1;
      return saved.get(name)!.apply(this, args);
    };
  }
  try {
    // 两类线格式都过一遍：ISO（要按 UTC 瞬间换算）与墙钟串（按本地解释）
    dt('2026-10-05T02:00:00.000000Z');
    dt('2026-10-01 10:00:00');
    dt('not-a-date'); // 解析失败分支同样不许碰本地取法
    assert.deepEqual(
      counts,
      {},
      `dt() 用了本地取法（这些调用在 UTC 机器上不改变结果，但形态就是"手工时区算术"）：${JSON.stringify(counts)}`,
    );
  } finally {
    for (const [name, fn] of saved) proto[name] = fn;
  }
});

test('dt：空值与解析失败都不退化成 Invalid Date', () => {
  assert.equal(dt(null), '—');
  assert.equal(dt(undefined), '—');
  assert.equal(dt(''), '—');

  // 解析不出来时原样返回（宁可显示怪串，也不要 "Invalid Date"）
  assert.equal(dt('not-a-date'), 'not-a-date');
  assert.equal(dt('0000-00-00 00:00:00'), '0000-00-00 00:00:00');

  // 正常值里不能漏出 T/Z 这种机器串
  assert.ok(!dt('2026-10-05T02:00:00.000000Z').includes('T'));
  assert.ok(!dt('2026-10-05T02:00:00.000000Z').includes('Z'));
});
