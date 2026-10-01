/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import assert from 'node:assert/strict';
import test from 'node:test';
import { PAGE_SIZE, pageQuery, totalOf } from './paging.ts';

test('pageQuery：page_size 扇出成 limit/size/per_page 三个别名（后端三套参数名）', () => {
  assert.deepEqual(pageQuery(undefined, 2), {
    page: 2,
    page_size: PAGE_SIZE,
    limit: PAGE_SIZE,
    size: PAGE_SIZE,
    per_page: PAGE_SIZE,
  });
  // 三套参数名的默认值不同（limit 15 / size 20 / per_page 20）：漏发任何一个，那一族就退回自己的
  // 默认值，而页数按 page_size 算 ⇒ 尾页取不到
  for (const key of ['page_size', 'limit', 'size', 'per_page']) {
    assert.equal(pageQuery(undefined, 3, 50)[key], 50, `${key} 没跟着 pageSize 走`);
  }
});

test('pageQuery：翻页要把调用方的筛选条件原样带上（甩掉就是「第二页是另一个列表」）', () => {
  assert.deepEqual(pageQuery({ game_id: 'g1', keyword: 'abc' }, 2), {
    game_id: 'g1',
    keyword: 'abc',
    page: 2,
    page_size: PAGE_SIZE,
    limit: PAGE_SIZE,
    size: PAGE_SIZE,
    per_page: PAGE_SIZE,
  });
  // 不改动调用方传进来的对象（useApi 拿它做依赖键，就地改会让缓存键悄悄变）
  const query = { status: 1 };
  pageQuery(query, 2);
  assert.deepEqual(query, { status: 1 });
});

test('totalOf：两种响应形状都取 data.total，读不出来退回本页行数', () => {
  // {list,total}（多数列表）与 {total,items}（风控/search）—— 键都叫 total
  assert.equal(totalOf({ list: [], total: 42, page: 1, limit: 20 }), 42);
  assert.equal(totalOf({ total: '7', items: [] }), 7);
  assert.equal(totalOf({ list: [], total: 0 }), 0);
  // 整表端点 / 裸数组 / 树：没有 total ⇒ 退回本页行数，页数算成 1，不画分页条
  assert.equal(totalOf({ list: [{ id: 1 }, { id: 2 }] }, 2), 2);
  assert.equal(totalOf([{ id: 1 }], 1), 1);
  assert.equal(totalOf(null, 5), 5);
  // 读不出数字的 total 不算数（别拿 NaN 去算页数）
  assert.equal(totalOf({ total: 'abc' }, 3), 3);
});
