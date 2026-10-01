/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
/**
 * i18n 运行时：当前语言（模块级，供请求层读）+ 查表 + 占位符 + 订阅。
 * 与两棵 flutter 的 `LocaleController`/`AppTranslations` 同构，差别只在 React 侧的绑定方式。
 *
 * 13 种语言**各一张全量表**（`TABLES`，与 `languages.ts` 的 `LANGUAGES` 逐项对应），
 * `t` 在 `TABLES[code]` 里查不到某个键才走 `en`，再查不到回键名本身
 * （与 flutter 的 `_data[locale]?[key] ?? _data['en']?[key] ?? key` 逐级同款）。
 */
import { useSyncExternalStore } from 'react';
import { en, type MessageKey } from './en.ts';
import { zh } from './zh.ts';
import { ja } from './ja.ts';
import { ko } from './ko.ts';
import { ru } from './ru.ts';
import { de } from './de.ts';
import { fr } from './fr.ts';
import { es } from './es.ts';
import { pt } from './pt.ts';
import { hi } from './hi.ts';
import { ar } from './ar.ts';
import { bn } from './bn.ts';
import { id } from './id.ts';
import { DEFAULT_CODE, resolve } from './languages.ts';

export { LANGUAGES, DEFAULT_CODE, resolve } from './languages.ts';
export type { Language } from './languages.ts';
export type { MessageKey } from './en.ts';

/** 语言表：**13 种短码各一张全量表**（顺序照 `LANGUAGES`），单个键查不到才回落 `en`。
 * 导出是给 `i18n.test.ts` 用的：「切换器列了 13 种、却有一种没注册表」是**静默**故障 ——
 * 那一项永远显示英文，只有「每种都真有表 + 键集与 en 逐项相同」这条断言抓得住。 */
export const TABLES: Record<string, Record<string, string>> = {
  en,
  zh,
  ja,
  ko,
  ru,
  de,
  fr,
  es,
  pt,
  hi,
  ar,
  bn,
  id,
};

/** localStorage 键。前缀与 lib/api.ts 的三个会话键同款（`react_admin_`），便于整体清理。 */
export const LOCALE_KEY = 'react_admin_locale';

/** 13 种里只有阿拉伯语是 RTL（bn/hi 等都是 LTR，别想当然把"非拉丁"都当 RTL）。 */
const RTL_CODES = new Set(['ar']);

/** 该语言是否从右往左。导出给用例钉「13 种里恰好只有 ar 是 RTL」。 */
export function isRtl(code: string): boolean {
  return RTL_CODES.has(resolve(code).code);
}

/**
 * 把语言落到 DOM 上：`lang`（无障碍朗读、拼写检查）与 `dir`（书写方向）。
 *
 * 为什么必须有这一步：**文案翻了但 `dir` 还是 ltr，阿拉伯语界面就是从左往右排的** ——
 * 文本方向、标点位置、flex/grid 的起点全反，用户一眼看出不对。翻译只解决"字对不对"，
 * 这一行才解决"排得对不对"。
 *
 * ⚠ 只有**写死 `left`/`right` 的样式**不会被 `dir` 自动镜像（浏览器只镜像逻辑方向）。
 * 本树用的都是 flex + 逻辑间距，暂不需要逐条改；将来加绝对定位的浮层时要留意。
 */
function applyDocumentLocale(code: string): void {
  if (typeof document === 'undefined') return; // node --test 无 DOM
  const root = document.documentElement;
  root.lang = code;
  root.dir = RTL_CODES.has(code) ? 'rtl' : 'ltr';
}

/** 读偏好。node --test 下没有 localStorage（无 DOM 底座）—— 认不出来就落默认值，不抛。 */
function loadCode(): string {
  try {
    return localStorage.getItem(LOCALE_KEY) ?? DEFAULT_CODE;
  } catch {
    return DEFAULT_CODE;
  }
}

let current = resolve(loadCode()).code;
applyDocumentLocale(current); // 首屏就落：偏好里存的是 ar 时，不能等用户再切一次才镜像

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
  applyDocumentLocale(current);
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
 * 查表。键写错是**编译期**报错（`MessageKey = keyof typeof en`），运行期只剩「这个键当前语言没有译文」
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
 * **每个要跟着语言重绘的页面子树，根组件都得自己调一次本 hook** —— `TabPage` 就调了。
 * 早先这里写着「订阅只挂在布局层（Shell）即可覆盖全树，`<Outlet/>` 会随之重渲染」，
 * **那句是错的**：真机实测切到中文后，顶栏/侧栏/用户菜单都变中文，而页面里的按钮仍是
 * `New Admins` / `Edit`、表头仍是 `Username`（同一屏半中半英）。
 * React Router 的 `<Outlet/>` 不会因为父组件重渲染就把路由子树重绘一遍。
 *
 * 所以：页面里**不要在模块顶层预先算好文案**（`const NAV = [{ label: t(...) }]`），
 * 那样会冻在首次求值的语言上；页面组件本身要订阅，然后放渲染期现算。
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
