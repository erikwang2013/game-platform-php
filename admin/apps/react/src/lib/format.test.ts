/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import assert from 'node:assert/strict';
import test from 'node:test';
import { dash, isTimeKey, num, pick, when } from './format.ts';

test('dash：null/undefined/空串显示 —，0/false 不算空', () => {
  assert.equal(dash(null), '—');
  assert.equal(dash(undefined), '—');
  assert.equal(dash(''), '—');
  assert.equal(dash(0), '0');
  assert.equal(dash(false), 'false');
});

test('num：bcmath 金额串原样透传，不转 number 丢精度', () => {
  assert.equal(num('12345678901234567890.12'), '12345678901234567890.12');
  assert.equal(num(1234.5), '1,234.5');
  // Number.isFinite 只放过有限 number 走 toLocaleString，NaN/Infinity 落到 dash，即原样 String(值)
  assert.equal(num(Number.NaN), 'NaN');
  assert.equal(num(Infinity), 'Infinity');
});

test('pick：取第一个非空候选键，全空返回 undefined', () => {
  const row: Record<string, unknown> = { order_id: '0', id: '', hashid: null, name: 'x' };
  assert.equal(pick(row, ['id', 'order_id']), '0');
  assert.equal(pick(row, ['hashid', 'missing']), undefined);
  assert.equal(pick(row, ['name']), 'x');
});

test('isTimeKey / when：时间键识别，10 位按秒 13 位按毫秒', () => {
  assert.equal(isTimeKey('created_at'), true);
  assert.equal(isTimeKey('expireTime'), true);
  assert.equal(isTimeKey('username'), false);
  assert.equal(when(''), '—');
  assert.equal(when('2026-01-02 03:04:05'), '2026-01-02 03:04:05');
  assert.equal(when(1_700_000_000), when(1_700_000_000_000));
});
