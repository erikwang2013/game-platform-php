/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Row } from './api.service';
import { pairs } from './util';

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
              ? '是'
              : '否'
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

/** 详情键值对：pairs 的容错包装，结构未知时不炸页面 */
export function kvOf(v: unknown): { label: string; value: string | number }[] {
  try {
    return pairs(v ?? {});
  } catch {
    return [];
  }
}

/** 验证码图：data URI / http / 绝对路径原样用，纯 base64 补前缀 */
export function imgSrc(v: unknown): string {
  const s = String(v ?? '');
  if (!s) return '';
  if (s.startsWith('data:') || s.startsWith('http') || s.startsWith('/')) return s;
  return `data:image/png;base64,${s}`;
}
