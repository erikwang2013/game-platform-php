/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { DICT } from '../core/i18n/dictionary';
import { t, use } from '../core/i18n/i18n';
import { DASH } from '../core/util';
import { amountText, amountTone, TX_COLS, txLabel, WALLET_STATS } from './wallet-fields';

/**
 * 钱包/流水的两张声明表 + 两个金额纯函数。
 *
 * 这里钉的是**键集**（后端的 `type` 值域，见 wallet-fields.ts 的逐键写入点）与**金额方向
 * 只看字符串**这两件事 —— 值域或符号判据一变，这里必须一起变，否则界面上先露原键再露错颜色。
 */
describe('钱包：类型文案 txLabel', () => {
  beforeEach(() => use('zh'));

  it('12 个真值都有中文文案，且逐字等于 C 端那张表的原文', () => {
    // 键集抄自 C 端 apps/angular/src/app/pages/wallet.ts:82-95 的 TX_LABEL（四棵树一族）
    const want: Record<string, string> = {
      deposit: '充值',
      withdraw: '提现',
      refund: '退款',
      exchange_in: '兑换转入',
      exchange_out: '兑换转出',
      game_spend: '开局扣费',
      game_earn: '游戏派彩',
      activity_reward: '活动奖励',
      referral_bonus: '邀请奖励',
      lock: '冻结',
      unlock: '解冻',
      reconcile: '对账调整',
    };
    for (const [type, text] of Object.entries(want)) expect(txLabel(type)).toBe(text);
  });

  it('每个键在 DICT 里都能查到（查不到就会回落成原键，等于界面露英文）', () => {
    for (const type of [
      'deposit',
      'withdraw',
      'refund',
      'exchange_in',
      'exchange_out',
      'game_spend',
      'game_earn',
      'activity_reward',
      'referral_bonus',
      'lock',
      'unlock',
      'reconcile',
    ]) {
      expect(`tx.${type}` in DICT).toBe(true);
      // 回落判据查的是 DICT 而不是 t() 的返回值：t() 认不出的键会原样返回，
      // 那就成了把 `tx.mystery` 连前缀一起摆给用户
      expect(txLabel(type)).not.toBe(type);
    }
  });

  it('未知类型原样回落（宁可露英文原键，也不编一个名字、更不留空）', () => {
    expect(txLabel('mystery')).toBe('mystery');
    expect(txLabel('')).toBe('');
    expect(txLabel(null)).toBe('');
    expect(txLabel(undefined)).toBe('');
  });

  it('别加全仓零写入的类型（transfer_in/commission/adjust 那批没有写入口）', () => {
    for (const ghost of ['transfer_in', 'transfer_out', 'commission', 'adjust', 'bet', 'win']) {
      expect(`tx.${ghost}` in DICT).toBe(false);
    }
  });
});

describe('钱包：金额方向与显示', () => {
  it('方向只看字符串首字符，不 parseFloat', () => {
    expect(amountTone('-12.5')).toBe('neg');
    expect(amountTone('-0.00000001')).toBe('neg');
    expect(amountTone('12.5')).toBe('pos');
    expect(amountTone('12345678901234567890.12345678')).toBe('pos');
  });

  it('零是中性的（含负零），空值/非数字也中性', () => {
    expect(amountTone('0')).toBe('');
    expect(amountTone('0.00000000')).toBe('');
    expect(amountTone('-0.00000000')).toBe('');
    expect(amountTone('')).toBe('');
    expect(amountTone(null)).toBe('');
    expect(amountTone('备注文字')).toBe('');
    // 日期串不是金额（isNum 也不认它）
    expect(amountTone('2026-10-01')).toBe('');
  });

  it('显示：正数补 +（服务端不补），负数/零原样，空值走占位符', () => {
    expect(amountText('12.5')).toBe('+12.5');
    expect(amountText('-12.5')).toBe('-12.5');
    expect(amountText('0.00000000')).toBe('0.00000000');
    expect(amountText('')).toBe(DASH);
    expect(amountText(null)).toBe(DASH);
    // 非数字不加号（备注/异常值原样透出）
    expect(amountText('abc')).toBe('abc');
  });
});

describe('钱包：两张声明表', () => {
  beforeEach(() => use('zh'));

  it('钱包卡 4 个字段与后端的 data.wallet 对齐，标签都能查到词条', () => {
    expect(WALLET_STATS.map((s) => s.key)).toEqual([
      'balance',
      'frozen_balance',
      'total_earned',
      'total_spent',
    ]);
    for (const s of WALLET_STATS) expect(t(s.label)).not.toBe(s.label);
  });

  it('流水列不含 ref_type/ref_id（单据类型没有值域，ref_id 还可能是 null）', () => {
    expect(TX_COLS.map((c) => c.key)).toEqual([
      'type',
      'amount',
      'balance_after',
      'remark',
      'created_at',
    ]);
    for (const c of TX_COLS) expect(t(c.label)).not.toBe(c.label);
  });
});
