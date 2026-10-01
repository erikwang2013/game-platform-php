/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { describe, expect, it } from 'vitest';
import { depositAmountOk, money, moneyIsZero, moneyRaw } from './api.service';

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

/**
 * 金额展示格式化。
 *
 * 每条只钉**一个维度**，好让变异诊断不串台：下面三条变异各自只该打红一条用例。
 *  - 去掉三位分组        → 只红「整数部分三位分组」
 *  - 小数截回 2 位       → 只红「小数位去尾零…」
 *  - 换回 Number() 实现  → 红「分组」+「小数」（旧实现同时踩两个毛病，这是预期而非串扰）
 */
describe('money', () => {
  it('整数部分三位分组，位数一位不差', () => {
    expect(money('0')).toBe('0.00');
    expect(money('999')).toBe('999.00');
    expect(money('1000')).toBe('1,000.00');
    expect(money('1000000')).toBe('1,000,000.00');
    expect(money('-1234.5000')).toBe('-1,234.50');
    // DECIMAL(20,8) 的真实量级：2^53 以上，走 float 就不是这个数了
    expect(money('9007199254740993.12')).toBe('9,007,199,254,740,993.12');
    expect(money('12345678901234567890.12')).toBe('12,345,678,901,234,567,890.12');
  });

  it('小数位去尾零但至少两位，低于 0.01 的量一位都不丢', () => {
    // 逗号归「三位分组」那条钉；这里只看小数位，故比对前先去掉逗号
    const frac = (v: string) => money(v).replace(/,/g, '');
    expect(frac('1.5')).toBe('1.50');
    expect(frac('628.5000')).toBe('628.50');
    expect(frac('0.00000000')).toBe('0.00');
    expect(frac('1234.56780000')).toBe('1234.5678');
    expect(frac('0.00000001')).toBe('0.00000001'); // bcmath scale 8 的最小非零量，不许显示成 0.00
    expect(frac('999.99999999')).toBe('999.99999999');
    // 这两个是旧实现「舍入越过真值」的现场：…99999999 会进位成 100,000,000,000,000.00
    expect(frac('99999999999999.99999999')).toBe('99999999999999.99999999');
    expect(frac('9007199254740993.00000001')).toBe('9007199254740993.00000001');
  });

  it('真实负数保留负号，负零不带符号，正号丢弃，前导零丢弃', () => {
    expect(money('-0.5')).toBe('-0.50');
    // ⚠ 下面四条里的前两条**改前钉的是旧行为**（`'-0.00'`），现在反过来钉 `'0.00'`。
    // 旧实现只按匹配到的符号位给 `-`：`-0` / `-0.00000000` 这类**零值**被渲染成 `-0.00`。
    // 反过来是因为「负零」不是金额语义：bcmath 在 A==B 时（如 `bcsub('1','1',8)`）会吐
    // `-0.00000000` 这种串，照渲染会在流水/余额里出现 `-0.00`，读起来像欠款；而它的真实含义
    // 就是零。判别用去前导零的整数部分与去尾零的小数部分，纯字符串比较，不引入数值转换。
    expect(money('-0')).toBe('0.00');
    expect(money('-0.00000000')).toBe('0.00');
    expect(money('-0.00')).toBe('0.00');
    expect(money('-000.000')).toBe('0.00');
    expect(money('+5')).toBe('5.00'); // bcmath 不产 '+'，多余的正号按旧行为丢弃
    expect(money('+0.5')).toBe('0.50');
    expect(money('007.5')).toBe('7.50');
    expect(money('000.5')).toBe('0.50');
    expect(money('000123')).toBe('123.00');
  });

  it('缺值与非数字输入保持拆分前的原样', () => {
    expect(money(null)).toBe('0.00');
    expect(money(undefined)).toBe('0.00');
    expect(money('')).toBe('0.00');
    expect(money(' ')).toBe('0.00');
    expect(money('abc')).toBe('abc');
    expect(money('1.2.3')).toBe('1.2.3');
    expect(money(NaN)).toBe('NaN');
    expect(money(Infinity)).toBe('Infinity');
  });

  it('moneyRaw 原样回显后端串（title 悬停看真值），缺值回空串', () => {
    expect(moneyRaw('1234.56780000')).toBe('1234.56780000');
    expect(moneyRaw('0.00000001')).toBe('0.00000001');
    expect(moneyRaw(12.5)).toBe('12.5');
    expect(moneyRaw(null)).toBe('');
    expect(moneyRaw(undefined)).toBe('');
  });
});

/**
 * 「0 = 不限 / 免费」这类**展示分支**的判据。
 *
 * 它替换掉的是 `deposit.limit` / `tournaments.feeText` 里的 `Number(x) > 0` ——
 * 金额列过数值转型，正是本批要禁掉的写法。判据必须与 `money()` 同一套（都是字符串），
 * 否则会出现「显示 0.00 却判定为非零」这种自相矛盾的格子。
 */
describe('moneyIsZero', () => {
  it('各种写法的零都认：0 / 0.00 / 0.00000000 / 负零 / 前后空白', () => {
    expect(moneyIsZero('0')).toBe(true);
    expect(moneyIsZero('0.00')).toBe(true);
    expect(moneyIsZero('0.00000000')).toBe(true);
    expect(moneyIsZero('-0.00000000')).toBe(true); // 负零也是零（与 money() 的负零归一同一判据）
    expect(moneyIsZero('  0.0000  ')).toBe(true);
    expect(moneyIsZero(0)).toBe(true);
  });

  it('非零一位都不能漏：scale-8 最小非零量与 >2^53 的量级都不是零', () => {
    expect(moneyIsZero('0.00000001')).toBe(false);
    expect(moneyIsZero('628.5000')).toBe(false);
    expect(moneyIsZero('12345678901234567890.12')).toBe(false);
  });

  it('缺值与非数字串按零处理（与拆分前 Number(x) > 0 的 NaN 分支一致）', () => {
    expect(moneyIsZero(null)).toBe(true);
    expect(moneyIsZero(undefined)).toBe(true);
    expect(moneyIsZero('')).toBe(true);
    expect(moneyIsZero(' ')).toBe(true);
    expect(moneyIsZero('abc')).toBe(true);
    expect(moneyIsZero('1.2.3')).toBe(true); // 不成形的串不给「非零」结论
  });

  /**
   * 与旧写法 `!(Number(x) > 0)` 的**唯一**真实分歧：负数。
   * 旧写法把 `-5` 判成「零」（`-5 > 0` 为假 ⇒ 取反为真）⇒ 限额会渲染成「不限」、报名费会渲染成「免费」；
   * 新实现按「零 = 串里没有 1-9」判，负数是**非零**。
   * 调用点（`max_amount` / `entry_fee`）都是 unsigned DECIMAL，负数线上不可达，所以这条分歧打不出来；
   * 但仍然要钉住：否则上面 docblock 里「⚠ 负数在这里是『非零』」那句就只是句注释，
   * 把新实现换成旧式写法的变异将无人捕获（实测：不补本条时该变异零红）。
   */
  it('负数是非零（与旧式 !(Number(x) > 0) 的分歧点，调用点不可达但仍钉住）', () => {
    expect(moneyIsZero('-5')).toBe(false);
    expect(moneyIsZero('-628.5000')).toBe(false);
    expect(moneyIsZero('-0.00000001')).toBe(false); // 负的 scale-8 最小非零量也是非零
  });
});
