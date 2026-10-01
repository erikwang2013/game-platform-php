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
