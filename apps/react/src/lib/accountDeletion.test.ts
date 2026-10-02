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

test('注销面板真的调用抽出来的判定（不是抽了不用）', () => {
  // 本树没有 DOM 测试基建，页面接线只能在源码层钉。
  // **钉的是调用点形态，不是「出现过这个名字」** —— 面板必然 import 这三个符号，
  // 所以 `src.includes('deleteVerdict')` 那种写法永久为真，是假绿不是修复。
  //
  // 2026-10-02 更新：原先钉的 `setDelError(deleteErrorMessage(e))` 是**存翻好的串**，
  // 那是「文案冻在失败那一刻的语言上」的缺陷类（切语言后这一行不跟着变）。
  // 现形态是**存原始输入**（`{ kind, err }`）、判定留到渲染期 ⇒ 下面同时钉住
  // 「三个判定都被调用」与「在哪调」（渲染期，不在事件回调里）。
  //
  // 2026-10-02 二次更新（Me.tsx 532 行拆分）：调用点整体搬到了 `pages/MePanels.tsx`，
  // 于是**断言跟着调用点走**，并且补一条「页面真的渲染了那个面板」——
  // 拆分引入了「代码搬出去了、但没人再引用」这个新退化形态，光钉面板自己抓不到，
  // 那会变成「钉了一坨永远不执行的死代码」，比不钉更坏。
  // ⚠ 这仍是**源码串断言**：能抓「写法回归」与「搬走后失联」，抓不到「包一层再绕过去用」。
  const panel = readFileSync(new URL('../pages/MePanels.tsx', import.meta.url), 'utf8');
  assert.match(panel, /const verdict = deleteVerdict\(gone\);/);
  assert.match(panel, /setDelError\(\{ kind: 'submit', err: e \}\);/);
  assert.match(panel, /setDelError\(\{ kind: 'check', err: e \}\);/);
  assert.match(panel, /setDelError\(\{ kind: 'verdict', gone \}\);/);
  assert.match(panel, /deleteErrorMessage\(delError\.err\)/);
  assert.match(panel, /deleteUnknownMessage\(delError\.err\)/);

  const page = readFileSync(new URL('../pages/Me.tsx', import.meta.url), 'utf8');
  assert.match(page, /<DeletePanel \/>/);
});
