/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import assert from 'node:assert/strict';
import test from 'node:test';
import { columnsFrom } from './columns.ts';

/**
 * 列**取哪些**（表头文案与渲染那条链在 `components/table-headers.test.ts`）。
 *
 * 这里钉的是 `max` 的**作用域**：它挡的是「把响应里剩下的字段都摊上来」，不是用来截
 * **作者手写的 preferred 清单**。两者混在一起时缺陷是静默的 —— 列数看着正常，显式点名的列
 * 就是不出现（提现订单的 `payout_status` / `created_at` 排在第 9、10 位，被缺省的 8 砍掉：
 * 运营在提现订单列表上看不到「这单打款成没成」）。
 */
const keys = (rows: Record<string, unknown>[], preferred?: string[], max?: number, hide?: string[]): string[] =>
  columnsFrom(rows, preferred, max, hide).map((column) => column.key);

/** n 个字段的一行：k0..k(n-1)，值不重要，只有**键序**参与判定。 */
const row = (n: number) => Object.fromEntries(Array.from({ length: n }, (_, i) => [`k${i}`, i]));

test('preferred 比 max 长时，手写清单整份留下，且不再补其余字段', () => {
  // 10 个首选列、响应里另有 6 个字段：首选全在，且**没有**第 11 列
  const rows = [row(16)];
  const preferred = Array.from({ length: 10 }, (_, i) => `k${i}`);
  assert.deepEqual(keys(rows, preferred), preferred, '手写清单被 max 截了');
  // 精确串坐实「不补其余」：k10..k15 一个都不许进来
  assert.equal(keys(rows, preferred).includes('k10'), false);
});

test('preferred 比 max 短时，补足部分照旧只补到 max（行为一字未改）', () => {
  const rows = [row(16)];
  const picked = keys(rows, ['k0', 'k1', 'k2']);
  assert.equal(picked.length, 8, '补足口径变了：这条是本轮唯一不该动的东西');
  assert.deepEqual(picked.slice(0, 3), ['k0', 'k1', 'k2'], 'preferred 仍排在前面');
  assert.deepEqual(picked.slice(3), ['k3', 'k4', 'k5', 'k6', 'k7'], '其余按响应键序补足到满 8 列');
});

test('preferred 恰好等于 max 时与改动前逐字相同', () => {
  const rows = [row(16)];
  const preferred = Array.from({ length: 8 }, (_, i) => `k${i}`);
  assert.deepEqual(keys(rows, preferred), preferred);
});

test('没有 preferred 的兜底表（AutoView）仍被 max 挡在 8 列', () => {
  assert.equal(keys([row(16)]).length, 8, '裸键序那条路必须照旧截断，否则一张表会摊出十几个列');
  assert.equal(keys([row(16)], []).length, 8);
  // 显式传 max 时以它为准（AutoView 就是这么调的）
  assert.equal(keys([row(16)], [], 5).length, 5);
});

test('preferred 里点了响应中不存在的键：不占名额、也不凭空造列', () => {
  const rows = [row(3)];
  assert.deepEqual(keys(rows, ['k0', 'nope', 'k1']), ['k0', 'k1', 'k2'], 'k2 是补足的第三个，nope 不吃名额');
  // 全是幻键时退回纯兜底：max 照旧生效
  assert.equal(keys([row(16)], ['nope', 'nada']).length, 8);
});

test('hide 与 preferred 同时给：藏掉的键既不在 preferred 里也不被补进来', () => {
  const rows = [row(5)];
  const picked = keys(rows, ['k0', 'k3'], undefined, ['k1']);
  assert.deepEqual(picked, ['k0', 'k3', 'k2', 'k4']);
});

test('提现订单那张清单（10 列）整份进表 —— payout_status / created_at 不再被截', () => {
  // 形状照 /admin/v1/withdraw/orders 的真响应（后端回全字段，ORDER_COLUMNS 只排顺序）
  const rows = [
    {
      id: 'W1', order_no: 'WO01', user_id: 'U1', platform_amount: '100.0000', fiat_amount: '100.00',
      currency: 'USD', method: 'paypal', status: 'approved', payout_status: 'success',
      created_at: '2026-10-01 08:00:00', note: 'x', reviewed_by: 'A1',
    },
  ];
  const ORDER_COLUMNS = [
    'id', 'order_no', 'user_id', 'platform_amount', 'fiat_amount',
    'currency', 'method', 'status', 'payout_status', 'created_at',
  ];
  assert.deepEqual(keys(rows, ORDER_COLUMNS), ORDER_COLUMNS);
});
