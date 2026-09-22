/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { describe, expect, it } from 'vitest';
import { depositAmountOk } from './api.service';

/** 充值金额精度预检：JPY/KRW 零小数，其余最多 2 位（与后端 DepositController 对齐） */
describe('depositAmountOk', () => {
  it('接受合法金额字符串', () => {
    for (const v of ['10', '10.5', '10.55', '0.01', '1000000']) {
      expect(depositAmountOk(v, 'USD'), v).toBe(true);
    }
  });

  it('拒绝超过 2 位小数', () => {
    for (const v of ['10.555', '0.001']) {
      expect(depositAmountOk(v, 'USD'), v).toBe(false);
    }
  });

  it('零小数币种不接受小数点（大小写不敏感）', () => {
    expect(depositAmountOk('10', 'JPY')).toBe(true);
    expect(depositAmountOk('10', 'jpy')).toBe(true);
    expect(depositAmountOk('10', 'KRW')).toBe(true);
    expect(depositAmountOk('10.5', 'JPY')).toBe(false);
    expect(depositAmountOk('10.5', 'krw')).toBe(false);
  });

  it('拒绝空串/非数字/负号/裸小数点', () => {
    for (const v of ['', ' ', 'abc', '-1', '10.', '.5', '1e3', '10,5']) {
      expect(depositAmountOk(v, 'USD'), JSON.stringify(v)).toBe(false);
    }
  });
});
