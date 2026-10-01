/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import assert from 'node:assert/strict';
import test from 'node:test';
import { alignTrend } from './trend.ts';

/**
 * `hit-trend` 的 series 对齐。值得钉的理由：**丢数据与「那天零命中」在图上同形**——
 * 各类型的 bucket 集合不等是常态（某天某类型没有命中，服务端的 group by 就不给那天的行），
 * 直接喂给只认第一个数组的渲染器会静默少画几条线，肉眼看不出来。
 */

test('各类型 bucket 不等时取并集，缺的补 0', () => {
  const { labels, lines } = alignTrend({
    device: [
      { bucket: '2026-09-29', hits: 3 },
      { bucket: '2026-09-30', hits: 5 },
    ],
    ip: [{ bucket: '2026-09-30', hits: 7 }],
  });

  assert.deepEqual(labels, ['2026-09-29', '2026-09-30']);
  assert.deepEqual(lines, [
    { name: 'device', values: [3, 5] },
    { name: 'ip', values: [0, 7] },
  ]);
});

test('横轴按日期字符串升序（服务端 bucket 是 %Y-%m-%d，字典序即时间序）', () => {
  const { labels } = alignTrend({
    a: [
      { bucket: '2026-10-01', hits: 1 },
      { bucket: '2026-09-09', hits: 1 },
      { bucket: '2026-09-30', hits: 1 },
    ],
  });
  assert.deepEqual(labels, ['2026-09-09', '2026-09-30', '2026-10-01']);
});

test('认不出的 bucket 不占位（塞进 0 号位会把数据挪到别的日期上）', () => {
  const { labels, lines } = alignTrend({
    a: [{ bucket: '2026-09-30', hits: 2 }],
    // 空 bucket 串在服务端不该出现，但真出现时不许把它并进横轴，也不许顶掉别人的值
    b: [{ hits: 9 }, { bucket: '2026-09-30', hits: 1 }],
  });
  assert.deepEqual(labels, ['2026-09-30']);
  assert.deepEqual(lines, [
    { name: 'a', values: [2] },
    { name: 'b', values: [1] },
  ]);
});

test('空 series 不抛错（端点回 {} 时渲染器自己显示「暂无数据」）', () => {
  assert.deepEqual(alignTrend({}), { labels: [], lines: [] });
});
