/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import assert from 'node:assert/strict';
import test from 'node:test';
import { money, moneyIsZero, moneyRaw } from './money.ts';

/**
 * 断言与 angular 树的 `money` / `moneyIsZero` / `moneyRaw` 用例**逐条同形**
 * （`apps/angular/src/app/core/api.service.spec.ts:42-151`），期望串一模一样。
 * 两边改一处必须改两处，否则「两棵树对同一个值印不同字符串」会重新长出来。
 *
 * 变异对照（把 `money()` 换回本树原先在 Deposit.tsx 里的 `Number()` + `toLocaleString` 版本）：
 *  - 回旧实现 → 红「分组」+「小数」+「负零」+「缺值」（旧实现同时踩四个毛病，这是预期而非串扰）；
 *  - 只把 `moneyIsZero` 换回 `!(Number(x) > 0)` → 红「负数是非零」那一条。
 */

test('money：整数部分三位分组，位数一位不差', () => {
  assert.equal(money('0'), '0.00');
  assert.equal(money('999'), '999.00');
  assert.equal(money('1000'), '1,000.00');
  assert.equal(money('1000000'), '1,000,000.00');
  assert.equal(money('-1234.5000'), '-1,234.50');
  // DECIMAL(20,8) 的真实量级：2^53 以上，走 float 就不是这个数了
  assert.equal(money('9007199254740993.12'), '9,007,199,254,740,993.12');
  assert.equal(money('12345678901234567890.12'), '12,345,678,901,234,567,890.12');
});

test('money：小数位去尾零但至少两位，低于 0.01 的量一位都不丢', () => {
  // 逗号归「三位分组」那条钉；这里只看小数位，故比对前先去掉逗号
  const frac = (v: string) => money(v).replace(/,/g, '');
  assert.equal(frac('1.5'), '1.50');
  assert.equal(frac('628.5000'), '628.50');
  assert.equal(frac('0.00000000'), '0.00');
  assert.equal(frac('1234.56780000'), '1234.5678');
  assert.equal(frac('0.00000001'), '0.00000001'); // bcmath scale 8 的最小非零量，不许显示成 0.00
  assert.equal(frac('999.99999999'), '999.99999999');
  // 这两个是旧实现「舍入越过真值」的现场：…99999999 会进位成 100,000,000,000,000.00
  assert.equal(frac('99999999999999.99999999'), '99999999999999.99999999');
  assert.equal(frac('9007199254740993.00000001'), '9007199254740993.00000001');
});

test('money：真实负数保留负号，负零不带符号，正号丢弃，前导零丢弃', () => {
  assert.equal(money('-0.5'), '-0.50');
  // 「负零」不是金额语义：bcmath 在 A==B 时（如 bcsub('1','1',8)）会吐 -0.00000000 这种串，
  // 照渲染会在流水/余额里出现 -0.00，读起来像欠款；而它的真实含义就是零。
  assert.equal(money('-0'), '0.00');
  assert.equal(money('-0.00000000'), '0.00');
  assert.equal(money('-0.00'), '0.00');
  assert.equal(money('-000.000'), '0.00');
  assert.equal(money('+5'), '5.00'); // bcmath 不产 '+'，多余的正号按旧行为丢弃
  assert.equal(money('+0.5'), '0.50');
  assert.equal(money('007.5'), '7.50');
  assert.equal(money('000.5'), '0.50');
  assert.equal(money('000123'), '123.00');
});

test('money：缺值与非数字输入保持原样', () => {
  assert.equal(money(null), '0.00');
  assert.equal(money(undefined), '0.00');
  assert.equal(money(''), '0.00');
  assert.equal(money(' '), '0.00');
  assert.equal(money('abc'), 'abc');
  assert.equal(money('1.2.3'), '1.2.3');
  assert.equal(money(NaN), 'NaN');
  assert.equal(money(Infinity), 'Infinity');
});

test('moneyRaw 原样回显后端串（title 悬停看真值），缺值回空串', () => {
  assert.equal(moneyRaw('1234.56780000'), '1234.56780000');
  assert.equal(moneyRaw('0.00000001'), '0.00000001');
  assert.equal(moneyRaw(12.5), '12.5');
  assert.equal(moneyRaw(null), '');
  assert.equal(moneyRaw(undefined), '');
});

test('moneyIsZero：各种写法的零都认：0 / 0.00 / 0.00000000 / 负零 / 前后空白', () => {
  assert.equal(moneyIsZero('0'), true);
  assert.equal(moneyIsZero('0.00'), true);
  assert.equal(moneyIsZero('0.00000000'), true);
  assert.equal(moneyIsZero('-0.00000000'), true); // 负零也是零（与 money() 的负零归一同一判据）
  assert.equal(moneyIsZero('  0.0000  '), true);
  assert.equal(moneyIsZero(0), true);
});

test('moneyIsZero：非零一位都不能漏：scale-8 最小非零量与 >2^53 的量级都不是零', () => {
  assert.equal(moneyIsZero('0.00000001'), false);
  assert.equal(moneyIsZero('628.5000'), false);
  assert.equal(moneyIsZero('12345678901234567890.12'), false);
});

test('moneyIsZero：缺值与非数字串按零处理（与拆分前 Number(x) > 0 的 NaN 分支一致）', () => {
  assert.equal(moneyIsZero(null), true);
  assert.equal(moneyIsZero(undefined), true);
  assert.equal(moneyIsZero(''), true);
  assert.equal(moneyIsZero(' '), true);
  assert.equal(moneyIsZero('abc'), true);
  assert.equal(moneyIsZero('1.2.3'), true); // 不成形的串不给「非零」结论
});

test('moneyIsZero：负数是非零（与旧式 !(Number(x) > 0) 的分歧点，调用点不可达但仍钉住）', () => {
  assert.equal(moneyIsZero('-5'), false);
  assert.equal(moneyIsZero('-628.5000'), false);
  assert.equal(moneyIsZero('-0.00000001'), false); // 负的 scale-8 最小非零量也是非零
});

/* ------------------------------------------------------------------ *
 * 页面级钉子：调用点显示串与 angular 同页逐字节相同
 * （本树无 jsdom / testing-library，渲染层测不了；钉的是页面渲染表达式
 *   真正调用的这个边界 —— 与 pages 里 `money(...)` / `moneyIsZero(...)`
 *   的两条分支同形，改任一处都会让下面这些串变样。）
 * ------------------------------------------------------------------ */

test('Tournaments 报名费「免费」分支：与 angular tournaments.ts:329 同串', () => {
  // angular: `moneyIsZero(t.entry_fee) ? '免费' : money(t.entry_fee)`
  // DECIMAL(18,4) 的三种真实取值
  assert.equal(moneyIsZero('0.0000') ? '免费' : money('0.0000'), '免费');
  assert.equal(moneyIsZero('10.0000') ? '免费' : money('10.0000'), '10.00');
  // 旧 react 写法 `Number('10.0650') > 0 ? '10.0650' : '免费'` 会原样吐 '10.0650'（无千分位、4 位小数），
  // 而 angular 印的是 money() 规整过的 '10.065' —— 两串在这里对拍
  assert.equal(moneyIsZero('10.0650') ? '免费' : money('10.0650'), '10.065');
  // bcmath scale-4 的最小非零量不得显示成「免费」
  assert.equal(moneyIsZero('0.0001') ? '免费' : money('0.0001'), '0.0001');
});

test('Leaderboard 金额榜分数：与 angular leaderboard.ts 的 score() 同串', () => {
  // angular: `return money(v)`（earned / spent 都是四位 DECIMAL）
  // 旧 react 版 `Number('0.0001').toLocaleString('en-US', {maximumFractionDigits: 2})` → '0'（非零显示成零）
  assert.equal(money('0.0001'), '0.0001');
  assert.equal(money('1234.5678'), '1,234.5678');
  assert.equal(money('99999999999999.9999'), '99,999,999,999,999.9999');
});

test('Deposit 到账平台币：与 angular money() 同串（旧 react 版在此向上舍入）', () => {
  // platform_amount = bcmul(amount, rate, 4)；rate=1.005、amount=10.01 ⇒ 服务端记 10.0650
  // 旧 react 版 Number('10.0650').toLocaleString(..., maximumFractionDigits: 2) → '10.07'，
  // 比服务端记账值大 1 分；angular 印 '10.065'
  assert.equal(money('10.0650'), '10.065');
  assert.equal(money('5000.0000'), '5,000.00');
});
