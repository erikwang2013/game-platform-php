/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import assert from 'node:assert/strict';
import test from 'node:test';
import { amountClass, dash, isTimeKey, num, pick, toWallClock, when } from './format.ts';

test('amountClass：只读字符串判方向，零与坏值都不着色', () => {
  // 正负两态：首字符判，不经 float
  assert.equal(amountClass('100.00000000'), 'delta up');
  assert.equal(amountClass('0.00000001'), 'delta up'); // 极小正额也是正
  assert.equal(amountClass('+5.00'), 'delta up');
  assert.equal(amountClass('-0.50000000'), 'delta down');
  assert.equal(amountClass('-100.00000000'), 'delta down');
  // 零不着色（染绿 = 把 0 说成收入）
  assert.equal(amountClass('0.00000000'), '');
  assert.equal(amountClass('0'), '');
  assert.equal(amountClass('-0.00000000'), '');
  // 空/坏值不着色，且不抛
  assert.equal(amountClass(''), '');
  assert.equal(amountClass(null), '');
  assert.equal(amountClass(undefined), '');
  assert.equal(amountClass('abc'), '');
  // 超长精度串不丢精度：它压根没被当数字读过（`Number('12345678901234567890.12')` 会变成 1.2345678901234568e+19）
  assert.equal(amountClass('12345678901234567890.12'), 'delta up');
  assert.equal(amountClass('-12345678901234567890.12'), 'delta down');
  // 这一条是**分道向量**：上面那些向量 `Number()`/`parseFloat()` 的答案与按串数位完全一致
  // （都能答对），换成 float 实现照样绿。深层小数会下溢成 0 ⇒ 只有「串里有非 0 数字」这条判据
  // 答得对。bcmath 没有小数位上限，这不是编出来的形状。
  assert.equal(amountClass(`0.${'0'.repeat(400)}1`), 'delta up', 'Number() 会把它下溢成 0 判成「零」');
  assert.equal(amountClass(`-0.${'0'.repeat(400)}1`), 'delta down');
});

test('dash：null/undefined/空串显示 —，0/false 不算空', () => {
  assert.equal(dash(null), '—');
  assert.equal(dash(undefined), '—');
  assert.equal(dash(''), '—');
  assert.equal(dash(0), '0');
  assert.equal(dash(false), 'false');
});

test('num：bcmath 金额串原样透传，不转 number 丢精度', () => {
  assert.equal(num('12345678901234567890.12'), '12345678901234567890.12');
  assert.equal(num(1234.5), '1,234.5');
  // Number.isFinite 只放过有限 number 走 toLocaleString，NaN/Infinity 落到 dash，即原样 String(值)
  assert.equal(num(Number.NaN), 'NaN');
  assert.equal(num(Infinity), 'Infinity');
});

test('pick：取第一个非空候选键，全空返回 undefined', () => {
  const row: Record<string, unknown> = { order_id: '0', id: '', hashid: null, name: 'x' };
  assert.equal(pick(row, ['id', 'order_id']), '0');
  assert.equal(pick(row, ['hashid', 'missing']), undefined);
  assert.equal(pick(row, ['name']), 'x');
});

test('isTimeKey / when：时间键识别，10 位按秒 13 位按毫秒', () => {
  assert.equal(isTimeKey('created_at'), true);
  assert.equal(isTimeKey('expireTime'), true);
  assert.equal(isTimeKey('username'), false);
  assert.equal(when(''), '—');
  // ⚠ 契约变更（2026-10-01）：ISO-UTC → 墙钟串的归一**不在这一层做**，在 `lib/api.ts` 的
  // 信封解析里（唯一 chokepoint，口径见 format.ts 的 toWallClock）。这条钉的是
  // 「**不要把已经是墙钟的串**当 UTC 再偏移一次」——那 8 个不带 cast 的列正好是墙钟串。
  assert.equal(when('2026-01-02 03:04:05'), '2026-01-02 03:04:05');
  // 传进来的若仍是 ISO 串，等于上游漏了归一；这里**故意**不兜底（兜了就又是同一个变换的
  // 第二个真值源），所以钉住「原样透传」是**在描述这个边界，不是认可这个显示效果**。
  assert.equal(when('2025-12-31T16:00:00.000000Z'), '2025-12-31T16:00:00.000000Z');
  assert.equal(when(1_700_000_000), when(1_700_000_000_000));
});

test('toWallClock：ISO-UTC 串归一到服务端墙钟串（+8），别的形状一格不动', () => {
  // 输入取**后端实测吐出来的形状**：库内 2026-01-01 00:00:00（+8）→ 响应 ...T16:00:00.000000Z
  assert.equal(toWallClock('2025-12-31T16:00:00.000000Z'), '2026-01-01 00:00:00');
  assert.equal(toWallClock('2026-09-16T16:38:51.000000Z'), '2026-09-17 00:38:51');
  assert.equal(toWallClock('2025-12-31T16:00:00Z'), '2026-01-01 00:00:00'); // 无小数秒也是真实形状
  assert.equal(toWallClock('2026-12-31T20:00:00.000000Z'), '2027-01-01 04:00:00'); // 跨年
  assert.equal(toWallClock('2026-01-01T00:00:00.000000Z'), '2026-01-01 08:00:00'); // 同日不进位

  // ⬇ 以下全是**不该被动**的形状。前两条就是那 8 个不带 cast 的列（本来就是墙钟串）：
  //   归一要是把它们也偏移 8 小时，等于用一个新缺陷换掉旧缺陷。
  assert.equal(toWallClock('2026-01-02 03:04:05'), '2026-01-02 03:04:05');
  assert.equal(toWallClock('2026-01-02 03:04:05.000'), '2026-01-02 03:04:05.000');
  assert.equal(toWallClock(''), '');
  assert.equal(toWallClock('abc'), 'abc');
  assert.equal(toWallClock('2026-01-02'), '2026-01-02'); // 纯日期不是时间戳
  // 带偏移的 ISO 不认（本仓后端只吐 `Z` 形与墙钟形两种；认了就得猜偏移，反而多一个真值源）
  assert.equal(toWallClock('2026-01-02T03:04:05+08:00'), '2026-01-02T03:04:05+08:00');
  assert.equal(toWallClock('2026-13-45T00:00:00Z'), '2026-13-45T00:00:00Z'); // 形似但非法，不吞

  // 读数与**宿主时区无关**：实现走 getUTC* 而非 getHours()。这条在本机（TZ=PRC=+8）与
  // 本地时区恰好重合 ⇒ 单跑证明不了独立性，判据是 `TZ=America/New_York npm test` 同样全绿。
  assert.equal(toWallClock('2025-12-31T16:00:00.000000Z'), '2026-01-01 00:00:00');
});

// flattenTree 那组用例改到 lib/tree.test.ts：树现在带树标记（__depth/__kids/__lineage），
// 且权限树有了展开/折叠，摊平不再是「children 递归展开」一件事
