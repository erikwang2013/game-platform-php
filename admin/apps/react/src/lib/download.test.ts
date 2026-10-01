/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import assert from 'node:assert/strict';
import test from 'node:test';
import { filenameFrom } from './download.ts';

/**
 * 落盘文件名的解析。纯逻辑（downloadFile 的 DOM 那半截在本树无覆盖 —— `node --test` 没有 DOM，
 * 如实标注，见文件头）。
 *
 * 为什么值得钉：这三种写法在同一个后端里都出现过，而解析失败的后果是**静默**的
 * （退回 fallback，文件名变成 export.xlsx 而不是 export_users_20261001.xlsx），
 * 只有「按响应头逐字取名」这条断言抓得住。
 */

test('双引号写法（PHP 的 response()->download 默认姿态）', () => {
  assert.equal(filenameFrom('attachment; filename="export_users_20261001120000.xlsx"', 'fb'), 'export_users_20261001120000.xlsx');
});

test('裸写法（无引号）', () => {
  assert.equal(filenameFrom('attachment; filename=report_2026-09-01_2026-10-01.csv', 'fb'), 'report_2026-09-01_2026-10-01.csv');
});

test('RFC 5987 写法：filename* 优先，且按百分号解码（非 ASCII 名）', () => {
  const header = "attachment; filename=\"report.csv\"; filename*=UTF-8''%E6%8A%A5%E8%A1%A8.csv";
  assert.equal(filenameFrom(header, 'fb'), '报表.csv');
});

test('转义坏掉的 filename*：原样用，不抛（下载不该因为一个坏百分号就整条失败）', () => {
  assert.equal(filenameFrom("attachment; filename*=UTF-8''%E6%8A", 'fb'), '%E6%8A');
});

test('认不出来（缺头 / 空头 / 无 filename）一律退 fallback', () => {
  for (const header of [null, '', 'attachment', 'inline', 'attachment; filename=""', 'attachment; filename=']) {
    assert.equal(filenameFrom(header, 'fallback.xlsx'), 'fallback.xlsx', `${String(header)} 没退回 fallback`);
  }
});

test('名字里不许留路径成分（名字来自响应头，落盘前不该含目录）', () => {
  // 先按分隔符替换成 `.._.._etc_passwd`，再剥前导点 ⇒ 落成 `_.._etc_passwd`
  assert.equal(filenameFrom('attachment; filename="../../etc/passwd"', 'fb'), '_.._etc_passwd');
  assert.equal(filenameFrom('attachment; filename="a/b\\c.csv"', 'fb'), 'a_b_c.csv');
  // 前导点会被剥掉：`..x` 这类隐藏/相对名不该原样落到磁盘上
  assert.equal(filenameFrom('attachment; filename=".hidden.csv"', 'fb'), 'hidden.csv');
});

test('只剥前导点，不动名字中间的（`a.b.xlsx` 的后缀要留着）', () => {
  assert.equal(filenameFrom('attachment; filename="a.b.xlsx"', 'fb'), 'a.b.xlsx');
});
