/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
/**
 * 显示层格式化。只做呈现，绝不参与金额/比率运算 —— 所有金额与百分比均由
 * 服务端 bcmath 计算，前端原样透传字符串。
 */

/** 空值统一显示为 "—"。 */
export const dash = (value: unknown): string =>
  value === null || value === undefined || value === '' ? '—' : String(value);

/**
 * 千分位。仅对真正的 number 生效；字符串原样返回，
 * 因为 bcmath 金额串（如 "12345678901234567890.12"）转 number 会丢精度。
 */
export const num = (value: unknown): string =>
  typeof value === 'number' && Number.isFinite(value) ? value.toLocaleString('zh-CN') : dash(value);

/** hashid / 主键候选键，一律按字符串透传，不做 parseInt。 */
export const ID_KEYS = ['game_id', 'user_id', 'order_id', 'event_id', 'id', 'hashid'];

/** 从行对象按候选键取第一个有值的字段。 */
export function pick(row: Record<string, unknown>, keys: string[]): unknown {
  for (const key of keys) {
    const value = row[key];
    if (value !== null && value !== undefined && value !== '') return value;
  }
  return undefined;
}

const TIME_KEY = /(_at|_time|time|date|created|updated|expire)/i;

export const isTimeKey = (key: string): boolean => TIME_KEY.test(key);

/** 时间戳/日期串统一展示；10 位按秒、13 位按毫秒。 */
export function when(value: unknown): string {
  if (value === null || value === undefined || value === '') return '—';
  if (typeof value === 'number' && Number.isFinite(value)) {
    // ponytail: 秒/毫秒启发式判定，够用；若后端统一为 ISO 串可删掉此分支
    const ms = value > 1e12 ? value : value * 1000;
    return new Date(ms).toLocaleString('zh-CN');
  }
  return String(value);
}
