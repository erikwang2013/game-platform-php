/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
/**
 * i18n 运行时：当前语言（模块级，供请求层读）+ 查表 + 占位符 + 订阅。
 * 与两棵 flutter 的 `LocaleController`/`AppTranslations` 同构，差别只在 React 侧的绑定方式。
 *
 * 只有 `en` 一张回落表：其余 11 种语言**不建表**，`t` 在 `TABLES[code]` 里查不到就走 `en`，
 * 再查不到回键名本身（与 flutter 的 `_data[locale]?[key] ?? _data['en']?[key] ?? key` 逐级同款）。
 */
import { useSyncExternalStore } from 'react';
import { en, type MessageKey } from './en.ts';
import { zh } from './zh.ts';
import { DEFAULT_CODE, resolve } from './languages.ts';

export { LANGUAGES, DEFAULT_CODE, resolve } from './languages.ts';
export type { Language } from './languages.ts';
export type { MessageKey } from './en.ts';

/** 语言表：**只建 en + zh 两张全量表**，其余 11 种查不到即回落 en。 */
const TABLES: Record<string, Record<string, string>> = { en, zh };

/** localStorage 键。前缀与 lib/api.ts 的三个会话键同款（`react_admin_`），便于整体清理。 */
export const LOCALE_KEY = 'react_admin_locale';

/** 读偏好。node --test 下没有 localStorage（无 DOM 底座）—— 认不出来就落默认值，不抛。 */
function loadCode(): string {
  try {
    return localStorage.getItem(LOCALE_KEY) ?? DEFAULT_CODE;
  } catch {
    return DEFAULT_CODE;
  }
}

let current = resolve(loadCode()).code;

const listeners = new Set<() => void>();

/**
 * 当前语言短码 —— **给 `lib/api.ts` 的发请求读**（`X-Language` 头）。
 *
 * 用模块级变量而不是让请求层去拿 React 上下文：请求可以在任何组件、乃至组件树之外发起，
 * 而取不到上下文时抛异常会把请求打死。与 flutter 用静态字段 `LocaleController.currentCode`
 * 是同一个理由，同一处失败模式。
 */
export function currentCode(): string {
  return current;
}

/** 切语言：归一 → 落值 → 持久化 → 通知订阅者。认不出来的码落 `en`（与 flutter `_apply` 同款）。 */
export function setCode(code: string): void {
  current = resolve(code).code;
  try {
    localStorage.setItem(LOCALE_KEY, current);
  } catch {
    // 隐私模式 / 无 DOM：切这一次仍生效，只是下次进来回到默认值
  }
  for (const notify of listeners) notify();
}

/** 占位符替换：`{name}` → params.name。缺参时**原样留着**（看得见漏了哪个，而不是静默空掉）。 */
function fill(raw: string, params?: Record<string, string | number>): string {
  if (!params) return raw;
  return raw.replace(/\{(\w+)\}/g, (whole, name: string) =>
    name in params ? String(params[name]) : whole,
  );
}

/**
 * 查表。键写错是**编译期**报错（`MessageKey = keyof typeof en`），运行期只剩「当前语言没这张表」
 * 一种情况，落回 en；en 也没有（只有拿断言硬闯进来才可能）则回键名本身。
 */
export function t(key: MessageKey, params?: Record<string, string | number>): string {
  return fill(TABLES[current]?.[key] ?? en[key] ?? key, params);
}

function subscribe(notify: () => void): () => void {
  listeners.add(notify);
  return () => listeners.delete(notify);
}

/**
 * 组件侧绑定：`const { t, code, setCode } = useI18n()`。
 *
 * 订阅只挂在**布局层**（Shell 顶栏的切换器）即可覆盖全树：换语言时 Shell 重渲染，
 * `<Outlet/>` 下的路由元素随之重渲染，各页面在渲染期现调 `t()` 拿到新语言。
 * 所以页面里**不要在模块顶层预先算好文案**（`const NAV = [{ label: t(...) }]`），
 * 那样会冻在首次求值的语言上 —— 放函数体里现算。
 */
export function useI18n(): { code: string; setCode: (code: string) => void; t: typeof t } {
  const code = useSyncExternalStore(subscribe, currentCode);
  return { code, setCode, t };
}

/**
 * 表格**只读列**的标题键兜底：模块在 `CrudConfig.fields` 里没声明的字段
 * （`id` / `created_at` / 关联表带出来的 `user_name` 之类）退回同名的 `f.<字段名>`。
 *
 * 用 `in en` 现查、不维护映射表：以后往 `en.fields.ts` 加一个 `f.*` 键会被自动吃到，
 * 不会有「第二处清单要同步」的漂移面。返回 null = 表里也没有 ⇒ 调用方退回字段名本身。
 */
export function fieldLabelKey(name: string): MessageKey | null {
  const candidate = `f.${name}`;
  return candidate in en ? (candidate as MessageKey) : null;
}
