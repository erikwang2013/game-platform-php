/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Row } from './api.service';
import { colKey, t } from './i18n/i18n';
import { dt, pairs } from './util';

export interface Scalar {
  k: string;
  v: string;
}

/** 标量：字符串/数字/布尔/null —— 结构未知时用它兜底铺指标卡 */
export function isScalar(v: unknown): boolean {
  return v === null || v === undefined || ['string', 'number', 'boolean'].includes(typeof v);
}

/** 取对象的一层标量字段，数组/嵌套对象跳过（另行原样展示） */
export function scalarsOf(v: unknown): Scalar[] {
  if (!v || typeof v !== 'object' || Array.isArray(v)) return [];
  return Object.entries(v as Row)
    .filter(([, x]) => isScalar(x))
    .map(([k, x]) => ({
      k,
      v:
        x === null || x === undefined || x === ''
          ? '—'
          : typeof x === 'boolean'
            ? x
              ? t('app.yes')
              : t('app.no')
            : String(x),
    }));
}

/** 响应里的数组部分：按给定候选键探测，找不到再退回常见包装键 */
export function rowsAny(v: unknown, ...keys: string[]): Row[] {
  if (Array.isArray(v)) return v as Row[];
  if (!v || typeof v !== 'object') return [];
  const o = v as Row;
  for (const k of [...keys, 'list', 'items', 'rows', 'data', 'records']) {
    const hit = o[k];
    if (Array.isArray(hit)) return hit as Row[];
  }
  return [];
}

export function json(v: unknown): string {
  try {
    return JSON.stringify(v, null, 2) ?? String(v);
  } catch {
    return String(v);
  }
}

export function nested(v: unknown, ...keys: string[]): unknown {
  if (!v || typeof v !== 'object') return undefined;
  const o = v as Row;
  for (const k of keys) if (o[k] !== undefined) return o[k];
  return undefined;
}

const ID_KEYS = [
  'id',
  'hashid',
  'hash_id',
  'uuid',
  'user_id',
  'game_id',
  'order_id',
  'ticket_id',
  'rule_id',
  'event_id',
  'device_id',
];

/** hashid 字符串 ID：按常见键名探测，取不到返回 ''（调用方自行跳过） */
export function idOf(v: Row): string {
  for (const k of ID_KEYS) {
    const x = v[k];
    if (typeof x === 'string' && x) return x;
    if (typeof x === 'number') return String(x);
  }
  return '';
}

/**
 * 详情键值对（详情抽屉的取数口）：结构未知时不炸页面。
 *
 * ⚠ 不能直接把单条记录丢给 `pairs()`：那个函数是给**图表序列**用的，标量一律过 `num()`
 * （`Number(v)` 非有限数即 0）⇒ 一条记录里的 `username: 'ops'` 会显示成 `ops 0`。
 * 数字字段恰好是对的，所以这个错看着不像错（实测见 core/render.spec.ts）。
 * 记录一律**原样**取值（字符串就是字符串）；数组/序列仍走 pairs 的归一。
 *
 * 唯一的例外是 `dt()`：datetime cast 的列出网是 ISO8601-UTC，抽屉里得一并换算成服务端时区
 * （与表格单元格同一套口径 —— 同一行记录在两处显示成两个时刻就白改了）。它整串不匹配时原样返回，
 * 所以对普通字符串没有影响。
 *
 * `label` 是**键名的显示名映射**，缺省原样（等于不翻）—— 取数函数不该替调用方决定文案语言，
 * 而 `label: 'last_login_ip'` 这种裸字段名摆在中文「详情」标题下就是运营读不懂的数据库列名。
 * 详情抽屉一律传 `kvLabel`（字段名 → `col.<字段名>` 词条，与表格列头同一条兜底链）：
 * 同一份记录在抽屉与表格里必须同名，两处各翻一套就会对不上。
 *
 * ⚠ 缺省是「原样」而不是「翻译」：`render.spec.ts` 钉着取数本身的契约（键名进、键名出），
 * 而文案是展示层的事。**漏传 mapper 的后果是静默的**（又变回 snake_case），
 * 所以四个消费页各有一条用例钉住「info() 出来的是译文」（render.spec.ts 的末组）。
 */
export function kvOf(
  v: unknown,
  label: (key: string) => string = (key) => key,
): { label: string; value: string | number }[] {
  try {
    if (v && typeof v === 'object' && !Array.isArray(v)) {
      return Object.entries(v as Record<string, unknown>)
        .filter(([, x]) => typeof x === 'number' || typeof x === 'string')
        .map(([key, x]) => ({
          label: label(key),
          value: typeof x === 'string' ? dt(x) : (x as number),
        }));
    }
    return pairs(v ?? {});
  } catch {
    return [];
  }
}

/**
 * 详情键的显示名：接口字段名 → `col.<字段名>` 词条，查不到就返回字段名本身。
 *
 * 走的是 `util.colsOf()` 给表格列头用的**同一条兜底链**（`t(colKey(k) ?? k)`）——
 * 抽屉与表格显示同一份记录时必须同名，否则「列表里叫『真实姓名』、抽屉里叫 real_name」。
 * 词条没有的字段宁可露原名，也不拼一个查不到的键（那会显示成 `col.xxx`）。
 */
export function kvLabel(key: string): string {
  return t(colKey(key) ?? key);
}

/** 验证码图：data URI / http / 绝对路径原样用，纯 base64 补前缀 */
export function imgSrc(v: unknown): string {
  const s = String(v ?? '');
  if (!s) return '';
  if (s.startsWith('data:') || s.startsWith('http') || s.startsWith('/')) return s;
  return `data:image/png;base64,${s}`;
}
