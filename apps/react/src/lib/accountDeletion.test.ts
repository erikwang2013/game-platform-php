/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';
import { ApiError } from './api.ts';
import { deleteErrorMessage, deleteUnknownMessage, deleteVerdict } from './accountDeletion.ts';

/* 注销账号的三处判定：透出原文、回读定成功、无法判定不宣布成功。
   三处退化都静默出事——吞成「操作失败」用户不知道要先提现；
   回读判据取反会把「没注销掉」宣布成成功。 */

test('服务端拒绝原因原样透出，不吞成「操作失败」', () => {
  assert.equal(deleteErrorMessage(new ApiError(422, '请先提现所有余额后再注销账号')), '请先提现所有余额后再注销账号');
  assert.equal(deleteErrorMessage(new ApiError(422, '请输入 yes 确认注销')), '请输入 yes 确认注销');
  assert.equal(deleteErrorMessage(new Error('boom')), '网络异常，请稍后重试');
});

test('回读结论：只有 gone 才算注销成功；仍读得到必须如实报告', () => {
  assert.equal(deleteVerdict(true).ok, true);
  const still = deleteVerdict(false);
  assert.equal(still.ok, false);
  assert.match(still.message, /仍可读取/);
});

test('回读本身失败：贴「无法确认」而不是宣布成功', () => {
  assert.match(deleteUnknownMessage(new Error('network')), /^注销结果无法确认/);
  assert.match(deleteUnknownMessage(new ApiError(500, '服务端错误')), /服务端错误/);
});

test('Me.tsx 真的调用抽出来的判定（不是抽了不用）', () => {
  // 本树没有 DOM 测试基建，页面接线只能在源码层钉：三个活调用点必须存在
  const src = readFileSync(new URL('../pages/Me.tsx', import.meta.url), 'utf8');
  assert.match(src, /const verdict = deleteVerdict\(gone\);/);
  assert.match(src, /setDelError\(deleteErrorMessage\(e\)\);/);
  assert.match(src, /setDelError\(deleteUnknownMessage\(e\)\);/);
});
