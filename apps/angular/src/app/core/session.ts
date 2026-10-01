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

/**
 * 金额展示格式化。**全程字符串运算，不做任何数值转换**（禁 `Number()`/`parseFloat`/`parseInt`/隐式转型）：
 * 平台币/余额列是 `DECIMAL(20,8)`，过 float 后 2^53 以上直接变成另一个数（`12345678901234567890.12`
 * 会被舍成 `…567000`）；且最小非零量 `0.00000001` 截到 2 位会被显示成 `0.00`（非零显示成零）。
 * 规则：整数部分 3 位分组；小数去尾零但**至少保留 2 位**，去零后若仍有非零低位则一位都不丢。
 * 金额的加减乘除一律在服务端 bcmath，本函数只做展示，不参与任何运算。
 * 非数字输入（`NaN`/`Infinity`/自由文本）与 `null`/`undefined`/空串的行为保持拆分前的原样。
 */
export function money(v: Num | null | undefined): string {
  const s = String(v ?? '').trim();
  if (s === '') return '0.00'; // null / undefined / 空串：原样保留旧行为
  const m = /^([+-]?)(\d*)(?:\.(\d*))?$/.exec(s);
  if (!m || (!m[2] && !m[3])) return String(v); // NaN / Infinity / 'abc'：原样回显
  const int = (m[2] || '0').replace(/^0+(?=\d)/, '');
  const frac = (m[3] ?? '').replace(/0+$/, '');
  // 负零归一：`-0` / `-0.00000000` / `-0.00` 都是零，零不带符号（改前这里只按 `m[1]` 判，
  // 会把它们渲染成 `-0.00`）。bcmath 的 `bcsub` 在 A==B 时确实可能吐 `-0.00000000` 这类串，
  // 而「负零」不是金额语义 ⇒ 只保留**真实负数**的 `-`。
  // 判据用去掉前导零后的整数部分与去尾零后的小数部分，仍是纯字符串比较，不碰数值转换。
  const sign = m[1] === '-' && (int !== '0' || frac !== '') ? '-' : '';
  // 正号仍按旧行为丢弃（bcmath 不产 '+'）
  const tail = frac.length >= 2 ? frac : frac.padEnd(2, '0');
  return `${sign}${int.replace(/\B(?=(\d{3})+$)/g, ',')}.${tail}`;
}

/** 金额的**后端原始串**（完整精度），给展示元素的 title 悬停用；缺值回空串 */
export function moneyRaw(v: Num | null | undefined): string {
  return v == null ? '' : String(v);
}

/**
 * 金额是否为**零/无值**——纯字符串判定，不做数值转换。专给「0 = 不限 / 免费」这类**展示分支**用
 * （`deposit.limit` / `tournaments.feeText` 原先写的是 `Number(x) > 0`，那正是本批要禁掉的转型）。
 * 与 `money()` 同一套判据：去掉前后空白与符号后，串里只要出现 `1-9` 就不是零，
 * 故 `'0'` / `'0.00000000'` / `'-0.00'` 都是零，`'0.00000001'`（bcmath scale-8 最小非零量）不是。
 * 缺值与非数字串（`null`/`''`/`NaN`/自由文本）按**零**处理：与拆分前 `Number(x) > 0` 在
 * `NaN`/`''`/`null` 上的取值一致（都落「非正」分支）；`'Infinity'` 这种串不可能是 DECIMAL 列的读数。
 * ⚠ 负数在这里是「非零」（`'-5'` → `false`），它表达的是**零/非零**而不是数学上的 `> 0`；
 * 本仓 DECIMAL 金额列全为 unsigned，别拿它替代真正的正负判断。
 */
export function moneyIsZero(v: Num | null | undefined): boolean {
  const s = String(v ?? '').trim();
  return !/^[+-]?\d*\.?\d*$/.test(s) || !/[1-9]/.test(s);
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
