/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import assert from 'node:assert/strict';
import test from 'node:test';
import { t } from '../i18n/index.ts';
import { buildPdfTable, cellText } from './pdf-table.ts';

/**
 * PDF 导出载荷（POST /admin/v1/export/pdf 的 `type:'table'` 体）。
 * 值得钉的理由两条，**两条都是静默的**：
 * ① 屏幕上那两列（行尾动作 / 批量勾选）混进 PDF，只会多两列空白，不报错；
 * ② 若把渲染分支的返回值当单元格塞进去，长文本那一支是 React 元素，进 PDF 变成 `[object Object]`。
 */

test('cellText：空/布尔/数组/对象各有口径，对象不落成 [object Object]', () => {
  assert.equal(cellText(null), '—');
  assert.equal(cellText(undefined), '—');
  assert.equal(cellText(''), '—');
  assert.equal(cellText(true), t('app.yes'));
  assert.equal(cellText(false), t('app.no'));
  // 0 与 '' 都是「有值」：金额 0 / 计数 0 不能被当成空
  assert.equal(cellText(0), '0');
  assert.equal(cellText('abc'), 'abc');
  assert.equal(cellText([]), '—');
  assert.equal(cellText([1, 2]), t('table.items', { count: 2 }));
  assert.equal(cellText({ a: 1 }), '{…}');
});

test('屏幕上那两列不进 PDF（表头与单元格都不进）', () => {
  const payload = buildPdfTable(
    'T',
    [
      { key: 'amount', label: 'f.hits' },
      { key: '__actions', label: 'common.actions' },
      { key: '__pick', label: 'browser.pick' },
    ],
    [{ amount: 'W1', __actions: 'x', __pick: 'y' }],
  );

  assert.equal(payload.type, 'table');
  assert.deepEqual(payload.data.columns, [t('f.hits')]);
  assert.deepEqual(payload.data.rows, [['W1']]);
  // 判据按**译文**判：载荷里只有译文、不含字段名，按 '__actions' 判是恒真的假绿
  // （实测：把 UI_ONLY 换成空数组，那种写法照样通过）
  assert.equal(JSON.stringify(payload).includes(t('common.actions')), false);
  assert.equal(JSON.stringify(payload).includes(t('browser.pick')), false);
});

test('每行宽度恒等于表头宽度，且缺字段补「—」而不是错位', () => {
  const columns = [
    { key: 'a', label: 'f.from' },
    { key: 'b', label: 'f.to' },
  ];
  const payload = buildPdfTable('T', columns, [{ a: 1 }, { b: 2 }, {}]);

  for (const row of payload.data.rows) assert.equal(row.length, payload.data.columns.length);
  assert.deepEqual(payload.data.rows, [
    ['1', '—'],
    ['—', '2'],
    ['—', '—'],
  ]);
});
