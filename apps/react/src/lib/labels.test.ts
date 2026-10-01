/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
import assert from 'node:assert/strict';
import test from 'node:test';
import { tournamentTypeLabel, txLabel } from './labels.ts';

/* 两张枚举标签表。钉三件事：
   ① 逐键都在（少一条 ⇒ 用户直接看到英文原键，正是这次要修的缺陷）；
   ② 未知键回落原值（后端加类型时不能变空白/undefined）；
   ③ 已知零写入的死键**不许**被加回来（有棵树照抄了 transfer_in/commission 那批）。 */

// 服务端写入侧逐点核过的键（写入点见 labels.ts 注释）
const TX_KEYS = [
  'deposit',
  'withdraw',
  'refund',
  'exchange_out',
  'exchange_in',
  'game_spend',
  'game_earn',
  'activity_reward',
  'referral_bonus',
  'lock',
  'unlock',
  'reconcile',
];

test('流水类型：写入侧核过的每个键都有非空中文标签', () => {
  for (const key of TX_KEYS) {
    const label = txLabel(key);
    assert.equal(typeof label, 'string', `${key} 应当是字符串`);
    assert.ok(label.length > 0, `${key} 的标签不能为空`);
    assert.notEqual(label, key, `${key} 没有命中标签表（回落成了原键）`);
  }
});

test('流水类型：未知键回落原键而不是空白', () => {
  assert.equal(txLabel('some_new_type'), 'some_new_type');
  assert.equal(txLabel(''), '');
});

test('流水类型：exchange 两个方向的标签与资金符号同向', () => {
  // 平台币视角：买游戏币 ⇒ 平台币扣款（exchange_out，负额）；卖 ⇒ 到账（exchange_in，正额）。
  // 标反比露原键更糟——玩家会以为钱走反了。措辞与 angular 树逐词一致（两棵树同词）。
  assert.equal(txLabel('exchange_out'), '兑换转出');
  assert.equal(txLabel('exchange_in'), '兑换转入');
  // 方向写死在词上：「转出」配负额、「转入」配正额
  assert.match(txLabel('exchange_out'), /转出$/);
  assert.match(txLabel('exchange_in'), /转入$/);
});

test('流水类型：零写入的死键不许加回来', () => {
  for (const dead of ['transfer_in', 'transfer_out', 'commission', 'adjust', 'bet', 'win']) {
    assert.equal(txLabel(dead), dead, `${dead} 全仓零写入，不该有标签（bet/win 是 game_play_record.action）`);
  }
});

test('赛制类型：已推定值与未知值回落', () => {
  assert.equal(tournamentTypeLabel('weekly'), '每周赛');
  assert.equal(tournamentTypeLabel('daily'), '每日赛');
  assert.equal(tournamentTypeLabel('monthly'), '每月赛');
  // 该列是自由文本且本仓无写入侧，运营手填的值必须原样透出
  assert.equal(tournamentTypeLabel('周赛'), '周赛');
  assert.equal(tournamentTypeLabel(''), '');
});
