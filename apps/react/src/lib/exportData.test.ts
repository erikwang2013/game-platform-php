/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 导出的**纯逻辑**半截（文件名、序列化、计数行）。
 * DOM 那半截（`saveBlob` 的 `<a download>`）在本树无单测：`node --test` 没有 DOM，
 * 靠真机 e2e 验（那里会截下 createObjectURL 出来的 Blob 再读回内容）。
 */
import test from 'node:test';
import assert from 'node:assert/strict';
import { exportBlob, exportCounts, exportName } from './exportData.ts';
import type { ExportData } from './types.ts';

const data = (over: Partial<ExportData> = {}): ExportData => ({
  profile: {
    username: 'alice',
    nickname: '新昵称',
    email: 'a@b.c',
    phone: null,
    country: null,
    language: 'zh-CN',
    created_at: '2026-01-01 00:00:00',
  },
  wallet: { balance: '520.0000', total_earned: '600.0000', total_spent: '80.0000' },
  transactions: [],
  exchange_records: [],
  deposit_orders: [],
  withdraw_orders: [],
  oauth_accounts: [],
  exported_at: '2025-03-04 10:00:00',
  ...over,
});

/*
 * 罐头里的时刻**刻意不取「今天」**：若取当天日期，被测代码改成读本机时钟后，
 * 文件名里的 `YYYYMMDD` 段仍然对得上，判别力只剩时分秒 —— 那种断言在恰好撞上
 * 那一刻时会假绿。跨年日期让「名字来自服务端」在日期段上就分得开。
 */
test('exportName：只留服务端 exported_at 里的数字', () => {
  assert.equal(exportName('2025-03-04 10:00:00'), 'game-platform-export-20250304100000.json');
  // ⚠ 名字**不能**退回本机时钟：那会印出一个与服务端 exported_at 对不上的时刻（第二真值源）
  assert.equal(exportName(''), 'game-platform-export-unknown.json');
  assert.equal(exportName('   '), 'game-platform-export-unknown.json');
  // 脏值也剥干净，落盘名里不会带路径成分
  assert.equal(exportName('../../etc/2026'), 'game-platform-export-2026.json');
});

test('exportBlob：JSON 缩进两格、content-type 是 application/json，内容逐字段可解回', async () => {
  const d = data({ transactions: [{ id: 'T1', amount: '20.0000' }] });
  const b = exportBlob(d);
  assert.equal(b.type, 'application/json');
  const text = await b.text();
  assert.ok(text.includes('\n  "profile"'), '必须是缩进过的，压缩成一行等于没导出');
  // 原样往返：明细行是服务端全列的原样行，序列化不许挑字段
  assert.deepEqual(JSON.parse(text), d);
});

test('exportCounts：四类分别计数（相加成一个数就看不出缺了哪一类）', () => {
  assert.equal(exportCounts(data()), '流水 0 · 兑换 0 · 充值 0 · 提现 0');
  assert.equal(
    exportCounts(data({ transactions: [{}, {}], withdraw_orders: [{}] })),
    '流水 2 · 兑换 0 · 充值 0 · 提现 1',
  );
});
