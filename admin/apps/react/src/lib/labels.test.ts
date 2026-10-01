/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import assert from 'node:assert/strict';
import test from 'node:test';
import { LANGUAGES, TABLES, setCode, t } from '../i18n/index.ts';
import { en } from '../i18n/en.ts';
import { txLabel } from './labels.ts';

/* 流水类型标签。钉四件事：
   ① 12 个键在**每一张**语言表里都真有一条自己的文案（不是靠 `t()` 回落英文 —— 那是假绿：
      键在某张表缺失时 `t()` 照样回英文，测试照样绿，而那个语言的用户永远看到英文）；
   ② 同一语言内 12 条互不相同（防复制粘贴出一对同名标签）；
   ③ 未知键回落原键（后端加类型时不能变空白/undefined）；
   ④ 已知零写入的死键**不许**被加回来。 */

// 与服务端写入侧逐点核过的键（写入点见 labels.ts 的注释；C 端两棵树同键集）
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

test('流水类型：13 张语言表里逐个键都有自己的文案，且同语言内互不相同', () => {
  for (const { code } of LANGUAGES) {
    const table = TABLES[code];
    const seen = new Map<string, string>();
    for (const key of TX_KEYS) {
      const text = table[`tx.${key}`];
      assert.ok(
        typeof text === 'string' && text.length > 0,
        `${code} 的 tx.${key} 没落表或为空串（界面会回落成英文或原键）`,
      );
      const clash = seen.get(text);
      assert.equal(clash, undefined, `${code} 的 tx.${key} 与 tx.${clash} 同名（复制粘贴漏改？）：${text}`);
      seen.set(text, key);
    }
  }
});

test('流水类型：txLabel 按当前语言取自己的表（不是回落英文）', () => {
  setCode('zh');
  assert.equal(txLabel('deposit'), '充值');
  assert.equal(txLabel('withdraw'), '提现');
  assert.equal(txLabel('exchange_out'), '兑换转出');
  assert.equal(txLabel('exchange_in'), '兑换转入');
  assert.equal(txLabel('reconcile'), '对账调整');
  // 逐键与「本语言表里的那条」逐字相同：换成别的语言就取别的表
  for (const key of TX_KEYS) assert.equal(txLabel(key), TABLES.zh[`tx.${key}`], `zh 的 ${key} 没走自己的表`);

  setCode('en');
  assert.equal(txLabel('deposit'), en['tx.deposit']);
  for (const key of TX_KEYS) assert.equal(txLabel(key), TABLES.en[`tx.${key}`]);
});

test('流水类型：exchange 两个方向的措辞与资金符号同向', () => {
  // 平台币视角：买游戏币 ⇒ 平台币扣款（exchange_out，负额）；卖 ⇒ 到账（exchange_in，正额）。
  // 标反比露原键更糟 —— 运营会以为钱走反了。词序与 C 端两棵树一致。
  setCode('zh');
  assert.match(txLabel('exchange_out'), /转出$/);
  assert.match(txLabel('exchange_in'), /转入$/);
  setCode('en');
});

test('流水类型：未知键回落原键而不是空白', () => {
  setCode('zh');
  for (const code of ['en', 'zh', 'ar']) {
    setCode(code);
    assert.equal(txLabel('some_new_type'), 'some_new_type', `${code} 下未知键没回落原键`);
    assert.equal(txLabel(''), '', `${code} 下空串没原样返回`);
  }
  setCode('en');
});

test('流水类型：零写入的死键不许加回来', () => {
  for (const code of ['en', 'zh']) {
    setCode(code);
    for (const dead of ['transfer_in', 'transfer_out', 'commission', 'adjust', 'bet', 'win']) {
      assert.equal(
        txLabel(dead),
        dead,
        `${code} 下 ${dead} 全仓零写入，不该有标签（bet/win 是 game_play_record.action）`,
      );
    }
  }
  setCode('en');
});

test('流水类型：键真的落在 en 表里（否则整个回落链是「原键透传」的假绿）', () => {
  for (const key of TX_KEYS) assert.ok(`tx.${key}` in en, `en 表里没有 tx.${key}，txLabel 会原样吐回键名`);
  // 反向：这张表的键**只**是那 12 个，多出来的就是没人写、也不会有人看到的死键
  assert.deepEqual(
    Object.keys(en)
      .filter((key) => key.startsWith('tx.'))
      .sort(),
    TX_KEYS.map((key) => `tx.${key}`).sort(),
  );
  // 采样：确保 t() 真的能把键变成文案（不是「键在表里但值是键名」）
  setCode('en');
  assert.notEqual(t('tx.deposit'), 'tx.deposit');
  setCode('zh');
  assert.notEqual(t('tx.deposit'), 'tx.deposit');
  setCode('en');
});
