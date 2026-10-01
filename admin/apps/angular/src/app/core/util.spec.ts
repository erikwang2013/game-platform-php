/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { describe, expect, it, vi } from 'vitest';
import { dash, DASH, dt } from './util';

/**
 * 时间列的显示归一：后端 datetime cast 的列出网是 **ISO8601 UTC**（带 `Z` + 6 位小数秒），
 * 库里存的却是 +08 的墙上时刻 ⇒ 不换算就等于把同一行记录的时刻说错 8 小时。
 *
 * 下面每条 ISO 夹具都是**实测的形状**，不是编的：库里 `2026-10-01 12:00:00`（+08）经
 * Eloquent `serializeDate()` 出网就是 `2026-10-01T04:00:00.000000Z`（用内存 SQLite 起真
 * capsule 走真模型跑出来的读数）。**覆盖为零过**：本树源码/用例里原先 ISO-Z 字面量 0 处。
 */
describe('dt 时间列显示', () => {
  it('ISO-Z（6 位小数秒）→ 服务端时区（+08）的 YYYY-MM-DD HH:mm:ss', () => {
    // 库里 2026-10-01 12:00:00 (+08) 出网的样子
    expect(dt('2026-10-01T04:00:00.000000Z')).toBe('2026-10-01 12:00:00');
  });

  it('跨日回卷：UTC 前一天 16:00 是 +08 的次日的 00:00（日期也要跟着走）', () => {
    // 库里 2026-01-01 00:00:00 (+08)
    expect(dt('2025-12-31T16:00:00.000000Z')).toBe('2026-01-01 00:00:00');
  });

  it('小数秒位数不同也认（后端恒 6 位，但别把 3 位/0 位判成另一种形状）', () => {
    expect(dt('2026-10-01T04:00:00.123Z')).toBe('2026-10-01 12:00:00');
    expect(dt('2026-10-01T04:00:00Z')).toBe('2026-10-01 12:00:00');
  });

  /**
   * ⚠ **宿主是 +08 会让这一类 bug 半隐形**（react 树同款盲区，已实测）。把 `dt()` 换成过宽实现
   * （「宽松门 + `Date.parse` + 本地 getter」）再跑，读数如下（2026-10-01 实测，同一次变异）：
   *   - 本机（+08 宿主）：**4 红** —— 本用例的**日期串那一半**（`dt('2026-10-01')` 被
   *     `Date.parse` 当 UTC 解析 ⇒ +08 读出 `2026-10-01 08:00:00`）、「整串匹配」那条
   *     （`+08:00` 后缀串被就地归一成 `2026-10-01 04:00:00`）、以及**另一条既存用例**
   *     （risk-charts 规则效果卡两格读数跟着漂）、以及**下面那条进程内钉子**（它逮的正是
   *     本用例逮不到的那一路）⇒ 这个变异确实在全树位移墙钟值，不是空转。
   *     但 `dt('2026-10-01 09:00:00')`（本用例的墙钟串那一半）**仍绿** —— 墙钟值加 8 小时
   *     再减 8 小时 = 原值，过宽实现在 +08 上对这一路**恒等**，这正是宿主 +08 的盲区。
   *     两个读数（+08 的 4 红 / NY 的 9 红）都是**加钉子之后**重测的：加钉子前分别是 3 / 8，
   *     钉子自己贡献其中 1 条（它咬，且两个宿主上都咬）。
   *   - `TZ=America/New_York`：**9 红**（多出 5 条：4 条 ISO→+08 墙上时刻 + `kvOf` 那条）
   *     ⇒ 换宿主这一跑**不是空转**，它逮的正是 +08 上恒等的那一路。
   * 所以判据是两道，缺一不可：
   *   ① 实现走 `Date.UTC` + `toISOString()`（`util.ts` 的 `dt()`），**不许** `getHours()` /
   *      `toLocaleString()` 这类跟随宿主的取法 —— 本树 `util.ts`/`render.ts` 里上述串命中 **0**；
   *   ② 换宿主重跑 `TZ=America/New_York ./node_modules/.bin/ng test --watch=false`
   *      ⇒ 实测 **24 文件 / 250 用例全绿**（与 +08 宿主同一读数）。这个数含下面那条钉子，
   *      加钉子前是 249；它会随用例增减漂，**以当场实测为准**（别当成常量抄）。
   * ② 是**手工读数**：取决于跑的人带不带 TZ。`.github/workflows/ci.yml` 四个 job（PHP 语法 /
   * composer 审计 / admin PHPUnit / service PHPUnit）**一个前端套件都不跑** ⇒ 本树（含这条）
   * 目前没有任何 CI 守卫，换宿主这条守不住，只能靠人记得跑。
   */
  it('naive 串**原样不变**：没有 cast 的时间列本来就是 +08，误伤就是凭空减 8 小时', () => {
    expect(dt('2026-10-01 09:00:00')).toBe('2026-10-01 09:00:00');
    expect(dt('2026-10-01')).toBe('2026-10-01');
  });

  /**
   * 判据①的**进程内**版本：`dt()` 调用期间不许碰任何跟随宿主的取法。
   *
   * ⚠ 它**替代不了** `TZ=America/New_York` 那一跑（上面注释里的判据②），两条证的不是一回事：
   *   - 这条只证明 **`dt()` 自己的实现路径**不碰本地取法 —— 谁把它改回 `getHours()`，**在 +08 上也会红**
   *     （这正是加它的原因：`TZ=` 那一跑逮得到、但得有人记得带 `TZ=`；这条仓内 `npm test` 就跑）；
   *   - `TZ=` 是**端到端**：同一份代码在两个宿主上读数相同，它才挡得住「别处新加了本地 getter」——
   *     本钉子只盯着这一次调用，树里任何别的渲染路径它都看不见。
   *
   * 计数必须在 `mockRestore()` **之前**读：vitest 的 `mockRestore` ＝ `mockReset` + 还原，
   * 还原后 `mock.calls` 一律是空的 ⇒ 先还原再断言就是一条**恒真**的钉子。
   */
  it('不碰本地取法：dt 调用期间 getHours/getFullYear/toLocaleString/getTimezoneOffset 全 0 次', () => {
    const LOCAL = [
      'getFullYear',
      'getMonth',
      'getDate',
      'getHours',
      'getMinutes',
      'getSeconds',
      'getTimezoneOffset',
      'toLocaleString',
      'toLocaleDateString',
      'toLocaleTimeString',
    ] as const;
    const spies = LOCAL.map((m) => vi.spyOn(Date.prototype, m));
    let calls: number[] = [];
    try {
      expect(dt('2026-10-01T04:00:00.000000Z')).toBe('2026-10-01 12:00:00'); // ISO-Z 那一路
      expect(dt('2026-10-01 09:00:00')).toBe('2026-10-01 09:00:00'); // 墙钟那一路（+08 上恒等的那条）
      calls = spies.map((s) => s.mock.calls.length);
    } finally {
      for (const s of spies) s.mockRestore();
    }
    expect(calls).toEqual(LOCAL.map(() => 0));
  });

  it('只有**整串**匹配才换算：正文里含 ISO 子串的备注/单号不许被改花', () => {
    expect(dt('备注 2026-10-01T04:00:00.000000Z 已收到')).toBe('备注 2026-10-01T04:00:00.000000Z 已收到');
    // 带偏移但非 Z（后端不发这种，别自作主张认领）
    expect(dt('2026-10-01T04:00:00+08:00')).toBe('2026-10-01T04:00:00+08:00');
  });
});

/**
 * `dash()` 是表格单元格与 Excel 导出**共用**的那一个咽喉（`components/table.ts` 与
 * `core/export.ts` 都调它，各写一份的话屏幕与导出会悄悄漂开）。
 */
describe('dash 走时间归一，其余分支不动', () => {
  it('字符串过 dt：表格与抽屉显示的是同一个时刻', () => {
    expect(dash('2026-09-30T14:11:00.000000Z')).toBe('2026-09-30 22:11:00');
  });

  it('非字符串分支照旧：空值走占位符、数字原样（金额是精确十进制串，别在这里被动过）', () => {
    expect(dash(null)).toBe(DASH);
    expect(dash(undefined)).toBe(DASH);
    expect(dash('')).toBe(DASH);
    expect(dash(0)).toBe('0');
    expect(dash('12345678901234567890.12')).toBe('12345678901234567890.12');
    expect(dash({ a: 1 })).toBe('{…}');
  });
});
