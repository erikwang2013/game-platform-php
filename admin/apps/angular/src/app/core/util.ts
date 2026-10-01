/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { colKey, t } from './i18n/i18n';

/** 缺失值占位符 —— 后端字段名未知时页面必须显示它而不是留空 */
export const DASH = '—';

/**
 * **服务端时区**（`admin/config/app.php:24` `'default_timezone' => 'Asia/Shanghai'`）。
 *
 * 后端把 datetime cast 的列以 **ISO8601 UTC（带 `Z` + 6 位小数秒）** 发出来
 * （Eloquent `serializeDate()` → `toISOString()`），而库里存的是 +08 的墙上时刻：
 * 库里 `2026-10-01 12:00:00` 出网就是 `2026-10-01T04:00:00.000000Z`。
 *
 * **不能拿浏览器本地时区换算**：同一条记录在东京会显示成 13:00、在 UTC 显示成 04:00，
 * 运维和客服照同一条记录对账时看到的时刻对不上，而且没人会想到是时区问题。
 * Asia/Shanghai 全年恒 +08、无夏令时，所以固定偏移就够（不必引 Intl / 时区库）。
 */
const SERVER_UTC_OFFSET_MS = 8 * 60 * 60 * 1000;

/**
 * 整串匹配才认：恰好是 `toISOString()` 的形状，别的一律不动。
 * 不用 `includes('T')` 之类的启发式 —— 那会把正文里恰好含日期子串的备注/订单号一起改花。
 */
const ISO_Z = /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})(?:\.\d+)?Z$/;

/**
 * ISO8601-UTC 串 → 服务端时区的 `YYYY-MM-DD HH:mm:ss`；**不是这个形状的原样返回**。
 *
 * 为什么不按 react 树那样在 **API 层**归一（有意差异，不是漂移）：本树的**写路径是安全的** ——
 * 时间列在表单里是 `date` validator（服务端），且 datetime cast 会把 ISO-Z 重新解析回 +08，
 * 来回一趟不失真；而 react 树有一个**可编辑字段直接落在 cast 时间列上**（`COUPON_FIELDS` 的
 * `start_at`/`end_at`），那棵树必须在 API 层拦，否则编辑框回写的就是 UTC 墙上时刻。
 * ⇒ **若本树将来出现同样的可编辑 cast 时间列，这里要跟着改到 API 层。**
 *
 * 目前的两处咽喉都是纯展示：`dash()`（表格单元格 + Excel 导出）与 `kvOf()`（详情抽屉）；
 * 表单预填走的是 `String(v)`／`json(v)`（form-modal），**不经过这里**，所以改写不到提交值。
 *
 * ponytail: 越界分量（`2026-13-45T…`）交给 Date 归一化成相邻月份，不做范围校验 ——
 * 线格式恒为 `toISOString()` 的输出、形状合法；真出现越界值那是后端坏了，不该在这里掩盖。
 */
export function dt(v: string): string {
  const m = ISO_Z.exec(v);
  if (!m) return v;
  const ms =
    Date.UTC(+m[1]!, +m[2]! - 1, +m[3]!, +m[4]!, +m[5]!, +m[6]!) + SERVER_UTC_OFFSET_MS;
  // 偏移后按 UTC 读回来 = 服务端时区的墙上时刻（toISOString 恒定宽度，切片即可，不受语言影响）
  const iso = new Date(ms).toISOString();
  return iso.slice(0, 10) + ' ' + iso.slice(11, 19);
}

/** 任意值 → 展示字符串；null/undefined/空串 → —；ISO8601-UTC 串 → 服务端时区时刻 */
export function dash(v: unknown): string {
  if (v === null || v === undefined || v === '') return DASH;
  if (typeof v === 'boolean') return v ? t('app.yes') : t('app.no');
  // 对象直接 String() 是 `[object Object]`：屏幕上是噪音，进了导出的 PDF 就是一份坏文件
  // （数组照旧走 String()，逗号连接比「n 项」有信息量）
  if (typeof v === 'object' && !Array.isArray(v)) return '{…}';
  return typeof v === 'string' ? dt(v) : String(v);
}

/**
 * 表格的列：键 + 已查表的表头。给了 heads 按它的键序，没给则从数据里推。
 * **ui-table 与 PDF 导出共用这一步** —— 各推一份的话，导出的列会与屏幕上那张表悄悄漂开。
 */
export function colsOf(
  heads: Record<string, string>,
  rows: Record<string, unknown>[],
): { key: string; label: string }[] {
  const keys = Object.keys(heads);
  if (keys.length) return keys.map((key) => ({ key, label: t(heads[key] ?? key) }));
  // 表头留空 = 从数据里推。顺序：共享的 col.<字段名> → 字段名本身；最后那级是刻意的 ——
  // 宁可露 `real_name`，也不许凭空拼一个查不到的键（那会显示成 col.xxx）
  const auto: string[] = [];
  for (const r of rows.slice(0, 20)) {
    for (const k of Object.keys(r)) if (!auto.includes(k)) auto.push(k);
  }
  return auto.slice(0, 10).map((key) => ({ key, label: t(colKey(key) ?? key) })); // ponytail: 自动列最多 10 列，超出靠 heads 显式指定
}

/** 任意值 → 有限数字，失败为 0（只用于图表几何/计数，金额一律保留字符串原样展示） */
export function num(v: unknown): number {
  const n = Number(v);
  return Number.isFinite(n) ? n : 0;
}

/**
 * 十进制数字串：**只用来看形状**（`isNum` 判列对齐用），不参与任何运算。
 *
 * 为什么需要它：本仓的金额/比率按 bcmath 契约出网时就是**字符串**
 * （`'1234.56000000'`、`'-12.5'`），原来只认 `typeof v === 'number'` ⇒ 金额列一列都不命中
 * `.num`，表格里金额左对齐、小数点对不齐（右边缘 spread 实测 43px）。`tabular-nums`
 * 只让数字等宽，**对齐要的是右对齐**，两回事。
 *
 * 刻意**不认**（形状不对就是文本，宁可左对齐也别把一串字右推）：
 * `'abc'` / `'2026-09-01'`（日期）/ `'138****0000'`（脱敏手机号）/ `''`（空串）/
 * `'1,234.00'`（千分位）/ `'1e5'`（科学计数）/ `'+1.5'`（bcmath 不会补正号）/
 * 前后带空白的 `' 12 '`。判据见 util.spec.ts。
 */
const DECIMAL = /^-?\d+(?:\.\d+)?$/;

/** 该值是否是「数字」（number，或十进制数字串）。表格据此把列右对齐 */
export function isNum(v: unknown): boolean {
  if (typeof v === 'number') return true;
  return typeof v === 'string' && DECIMAL.test(v);
}

export interface Pair {
  label: string;
  value: number;
}

/**
 * 时间序列归一化 —— 后端三种形状都吃：
 * 1. `{dates:[], values:[]}` / `{dates:[], arpu:[], arppu:[]}`（取第一个数字数组当 values）
 * 2. `{ "2026-09-01": 12, ... }` 映射
 * 3. `[{date|label, value|count}, ...]`
 * 认不出来就返回空数组，图表显示空态而不是抛错。
 */
export function pairs(input: unknown, valueKey?: string): Pair[] {
  if (Array.isArray(input)) {
    return input.map((row) => {
      const r = (row ?? {}) as Record<string, unknown>;
      const label = r['date'] ?? r['label'] ?? r['day'] ?? r['hour'] ?? r['name'] ?? '';
      const v = valueKey ? r[valueKey] : (r['value'] ?? r['count'] ?? r['total'] ?? r['num']);
      return { label: String(label), value: num(v) };
    });
  }
  if (input && typeof input === 'object') {
    const o = input as Record<string, unknown>;
    const dates = o['dates'] ?? o['labels'] ?? o['x'];
    if (Array.isArray(dates)) {
      const key =
        valueKey ??
        ['values', 'data', 'count', 'total', 'dau', 'arpu'].find((k) => Array.isArray(o[k]));
      const vals = key ? (o[key] as unknown[]) : [];
      return dates.map((d, i) => ({ label: String(d), value: num(vals[i]) }));
    }
    return Object.entries(o)
      .filter(([, v]) => typeof v === 'number' || typeof v === 'string')
      .map(([label, value]) => ({ label, value: num(value) }));
  }
  return [];
}

/** 取对象里的数组字段，非数组则空数组 */
export function rowsOf(input: unknown, ...keys: string[]): Record<string, unknown>[] {
  if (Array.isArray(input)) return input as Record<string, unknown>[];
  if (input && typeof input === 'object') {
    const o = input as Record<string, unknown>;
    for (const k of [...keys, 'list', 'items', 'rows', 'data']) {
      if (Array.isArray(o[k])) return o[k] as Record<string, unknown>[];
    }
  }
  return [];
}

/** 错误 → 人话 */
export function errText(e: unknown): string {
  if (e instanceof Error && e.message) return e.message;
  return t('app.loading_failed');
}

/** 简化枚举映射：字典查不到就原样回显 */
export function label(map: Record<string, string>, key: unknown): string {
  const k = String(key ?? '');
  return map[k] ?? (k || DASH);
}

/**
 * 0/1 状态列的文案（1 = `app.enabled` 启用 / 0 = `app.disabled` 停用）。
 *
 * 为什么要有它：这些列的值是**后端编码**（TINYINT 0/1），表头原来写着 `状态(0禁用/1启用)`
 * —— 把数据库编码当成了运营标签，运营得先背下来 0 和 1 各是什么意思。
 * 修法是**留住原值、另开一列**（`status_label`）显示文案：原值还要喂表单预填与行内动作
 * （`statused: true` 的启停就是拿它比 0/1），改写它就等于改了提交给后端的东西。
 *
 * 口径与 `app.enabled`/`app.disabled` 一致（本树 0/1 只有这一套措辞：券筛选、CDN 启停、
 * 角色启停、支付方式启停都用它）。
 *
 * 不是 0/1 的值**原样透出**（`'banned'` 这类别的 ID 空间的状态走自己的映射表）：
 * 把认不出的值说成「停用」是替后端编状态。空值走占位符，同理。
 */
export function enabledLabel(v: unknown): string {
  if (v === null || v === undefined || v === '') return DASH;
  // ⚠ 这里**不能走 num()**：它把认不出的值折成 0（`num('banned') === 0`）⇒ `'banned'`
  // 会被说成「停用」，正好踩上面那条「认不出的别编状态」。（util.spec.ts 钉着这条。）
  if (v === 1 || v === '1') return t('app.enabled');
  if (v === 0 || v === '0') return t('app.disabled');
  return String(v);
}
