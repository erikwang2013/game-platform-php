/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
/**
 * 会话与展示层基础设施（token 存取 / 金额与时间格式化 / 错误类型 / Bearer 拦截器）。
 *
 * 从 api.service.ts 原样拆出：那边要加新端点，逼近 500 行上限。
 * 对外仍由 api.service.ts 统一 re-export（`export * from './session'`），
 * 故既有的 `from '../core/api.service'` 导入路径全部不变。
 */
import { HttpInterceptorFn } from '@angular/common/http';
import type { Num } from './api.types';

/* ---------------- token 存取 ---------------- */

const K_ACCESS = 'gp_access_token';
const K_REFRESH = 'gp_refresh_token';

const store = {
  get: (k: string): string => {
    try {
      return localStorage.getItem(k) ?? '';
    } catch {
      return '';
    }
  },
  set: (k: string, v: string): void => {
    try {
      localStorage.setItem(k, v);
    } catch {
      /* 隐私模式下写入失败，静默降级为未登录 */
    }
  },
  del: (k: string): void => {
    try {
      localStorage.removeItem(k);
    } catch {
      /* ignore */
    }
  },
};

export const tokens = {
  access: (): string => store.get(K_ACCESS),
  refresh: (): string => store.get(K_REFRESH),
  save(access: string, refresh: string): void {
    store.set(K_ACCESS, access);
    if (refresh) store.set(K_REFRESH, refresh);
  },
  clear(): void {
    store.del(K_ACCESS);
    store.del(K_REFRESH);
  },
};

export const isAuthed = (): boolean => !!tokens.access();

/** 展示用格式化；不做金额运算，故允许 Number() 转换。 */
export function money(v: Num | null | undefined): string {
  const n = Number(v ?? 0);
  return Number.isFinite(n)
    ? n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
    : String(v);
}

/** 兼容 MySQL "YYYY-MM-DD HH:MM:SS"（Safari 需替换空格为 T） */
export function dt(s: string): string {
  if (!s) return '—';
  const d = new Date(s.replace(' ', 'T'));
  return Number.isNaN(d.getTime()) ? s : d.toLocaleString();
}

/** 充值零小数币种（与后端 DepositController 的精度校验一致） */
const ZERO_DECIMAL = ['JPY', 'KRW'];

/**
 * 充值金额精度预检，与后端同规则：JPY/KRW 零小数，其余最多 2 位小数。
 * 纯字符串格式校验，不做任何金额换算或舍入；后端仍会二次校验。
 */
export function depositAmountOk(amount: string, currency: string): boolean {
  const max = ZERO_DECIMAL.includes(currency.toUpperCase()) ? 0 : 2;
  return max === 0 ? /^\d+$/.test(amount) : new RegExp(`^\\d+(\\.\\d{1,${max}})?$`).test(amount);
}

/* ---------------- 错误 ---------------- */

export class ApiError extends Error {
  constructor(
    message: string,
    readonly code: number,
  ) {
    super(message);
    this.name = 'ApiError';
  }
}

/* ---------------- 拦截器：Bearer ---------------- */

export const authInterceptor: HttpInterceptorFn = (req, next) => {
  const token = tokens.access();
  return next(token ? req.clone({ setHeaders: { Authorization: `Bearer ${token}` } }) : req);
};
