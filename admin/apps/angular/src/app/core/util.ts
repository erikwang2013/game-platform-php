/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */

/** 缺失值占位符 —— 后端字段名未知时页面必须显示它而不是留空 */
export const DASH = '—';

/** 任意值 → 展示字符串；null/undefined/空串 → — */
export function dash(v: unknown): string {
  if (v === null || v === undefined || v === '') return DASH;
  if (typeof v === 'boolean') return v ? '是' : '否';
  return String(v);
}

/** 任意值 → 有限数字，失败为 0（只用于图表几何/计数，金额一律保留字符串原样展示） */
export function num(v: unknown): number {
  const n = Number(v);
  return Number.isFinite(n) ? n : 0;
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
  return '加载失败，请稍后重试';
}

/** 简化枚举映射：字典查不到就原样回显 */
export function label(map: Record<string, string>, key: unknown): string {
  const k = String(key ?? '');
  return map[k] ?? (k || DASH);
}
