/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import test from 'node:test';
import { columnsFrom } from '../lib/columns.ts';
import { fieldLabelKey } from '../i18n/index.ts';

/**
 * 列表**表头**的多语言（用户报过：「模块的列表标题没做多语言」）。
 *
 * 修复前的链路：`columnsFrom` 把字段名直接当标题（`label: key`），`DataTable` 渲染又不过 `t()`，
 * 于是任何语言下列头都是 `real_name` / `created_at` 这种裸字段名。这条钉三件事：
 *  ① 列标题能按映射取到键；② 没映射时**退化成字段名**（不冒出裸露的 `f.xxx`）；
 *  ③ 渲染点真的过了 `t()`——本树无 DOM 底座，这一环只能是读源码断言（如实标注）。
 */
const SOURCE = readFileSync(fileURLToPath(new URL('./DataTable.tsx', import.meta.url)), 'utf8');
const TAB_PAGE = readFileSync(fileURLToPath(new URL('../pages/TabPage.tsx', import.meta.url)), 'utf8');

test('列标题按映射取键；没映射的退回字段名本身', () => {
  const rows = [{ id: '1', username: 'a', mystery: 'x' }];
  const columns = columnsFrom(rows, ['id', 'username', 'mystery'], undefined, undefined, {
    username: 'f.username',
  });

  const byKey = Object.fromEntries(columns.map((c) => [c.key, c.label]));
  assert.equal(byKey.username, 'f.username', '模块声明过的字段要用它的标签键');
  assert.equal(byKey.id, 'id', '没映射的列退回字段名，不能凭空造键');
  assert.equal(byKey.mystery, 'mystery');
});

test('fieldLabelKey：同名 f.<字段名> 存在就给键，不存在给 null', () => {
  assert.equal(fieldLabelKey('username'), 'f.username');
  // 只读列（模块别处声明过、本模块没声明）也认得出
  assert.equal(fieldLabelKey('created_at'), 'f.created_at');
  assert.equal(fieldLabelKey('definitely_not_a_field'), null);
});


/**
 * **渲染点必须过 `t()`** —— 源码级（本树 `node --test` 无 jsdom）。
 * 少了这一环，`columnsFrom` 给出的键就永远只是键，界面上露 `f.username`。
 */
test('DataTable 的 th 渲染过 t()（源码级）', () => {
  assert.match(SOURCE, /\{t\(column\.label as MessageKey\)\}/, 'th 里必须 t(column.label)，否则键不会变成译文');
});

/**
 * **页面要自己订阅语言**（源码级）。布局层（Shell）重绘**不会**带动 `<Outlet/>` 下的页面重绘 ——
 * 真机实测：切到中文后顶栏/侧栏变中文，而页面里的按钮仍是 `New Admins` / `Edit`、表头仍是 `Username`。
 * 这一行没了，用户看到的就是半中半英。
 */
test('TabPage 自己订阅语言（源码级，防「切了语言页面不重绘」回退）', () => {
  const body = TAB_PAGE.slice(TAB_PAGE.indexOf('export function TabPage'));
  assert.match(body.slice(0, 600), /useI18n\(\)/, 'TabPage 必须调用 useI18n() 订阅；只靠 Shell 重绘不够');
});
