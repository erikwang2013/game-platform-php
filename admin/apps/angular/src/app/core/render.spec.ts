/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { kvOf } from './render';

/**
 * `kvOf` 是三个详情抽屉的唯一取数口（users / support / admins）。
 *
 * 坑在它委托的 `pairs()`：那个函数是给**图表序列**用的，标量一律过 `num()`（Number → 非有限数即 0）。
 * 拿它渲染一条记录，等于把 `username: 'ops'` 显示成 `ops 0`（数字字段恰好对，所以看着不像坏了）。
 * 详情是「有什么显示什么」，字符串必须原样出去。
 */
describe('kvOf 详情键值对', () => {
  it('单条记录：字符串原样保留，数字保留为数字', () => {
    expect(kvOf({ username: 'ops', real_name: '运营甲', status: 1, login_count: 12 })).toEqual([
      { label: 'username', value: 'ops' },
      { label: 'real_name', value: '运营甲' },
      { label: 'status', value: 1 },
      { label: 'login_count', value: 12 },
    ]);
  });

  it('跳过对象/数组/空值（嵌套结构不进键值对），不炸', () => {
    expect(kvOf({ id: 'A1', roles: [{ name: 'x' }], meta: { a: 1 }, note: null })).toEqual([
      { label: 'id', value: 'A1' },
    ]);
  });

  it('数字 0 与空串都算有值（0 是合法读数，不能被当成「没值」丢掉）', () => {
    expect(kvOf({ status: 0, remark: '' })).toEqual([
      { label: 'status', value: 0 },
      { label: 'remark', value: '' },
    ]);
  });

  it('图表序列照旧走 pairs（数组仍按 label/value 归一）', () => {
    expect(kvOf([{ day: '2026-09-30', count: 7 }])).toEqual([{ label: '2026-09-30', value: 7 }]);
  });

  it('时间列换算成服务端时区（抽屉与表格同一口径），普通字符串仍原样', () => {
    expect(kvOf({ last_login_at: '2026-10-01T04:00:00.000000Z', username: 'ops' })).toEqual([
      { label: 'last_login_at', value: '2026-10-01 12:00:00' },
      { label: 'username', value: 'ops' },
    ]);
  });
});
