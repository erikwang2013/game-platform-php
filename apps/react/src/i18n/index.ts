/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
/**
 * i18n 运行时：当前语言（模块级，供请求层读）+ 查表 + 占位符 + 订阅。
 *
 * **本文件刻意不 import react** —— `lib/http.ts`（传输层）要拿它翻错误文案，而那一层
 * 的自我约束是「不依赖 React」（原文写的是「零 import」，见该文件 :10 注释）。
 * 组件侧绑定在 `./useI18n.ts`，那里才引 react。
 *
 * 13 种语言**各一张全量表**，`t` 在 `TABLES[code]` 里查不到某个键才走 `en`，
 * 再查不到回键名本身（与 flutter 的 `_data[locale]?[key] ?? _data['en']?[key] ?? key` 同款）。
 *
 * ⚠ **首屏只静态带 `en`（逐键兜底）与 `zh`（本树默认语言）**，其余 11 张按需拉（`ensure`）。
 * 全量内联会让首屏多背 11 张表；而每个人只会用到一种。`en` 必须静态：它是 `t()` 的兜底，
 * 缺了它任何一张表漏键都会把键名直接渲染到界面上；`zh` 也必须静态：本树默认中文，
 * 懒加载会让每个中文用户先闪一下英文。
 */
import { en, type MessageKey } from './en.ts';
import { zh } from './zh.ts';
import { DEFAULT_CODE, isRtl, resolve } from './languages.ts';

export { LANGUAGES, DEFAULT_CODE, resolve, isRtl } from './languages.ts';
export type { Language } from './languages.ts';
export type { MessageKey } from './en.ts';

/**
 * 语言表集 —— **它同时就是运行期查表用的注册表**：首屏只有 `en`/`zh`，其余 11 张
 * 在 `ensure()` 到货时逐个补进来。
 *
 * ⚠ **应用代码不许引用本导出**：运行期查表一律走 `t()`。导出它只为 `node --test` 的
 * 结构断言（「切换器列了 13 种、却有一种没注册表」是**静默**故障），以及那条
 * 「删掉一个键看是否逐键回落 en」的用例（它得改到 `t()` 正读着的那个对象才作数，
 * 所以这里存的是**引用**不是拷贝）。
 */
export const TABLES: Record<string, Record<string, string>> = { en, zh };

/** 语言码 → 该语言的表。**一种语言一个 chunk**：切到日语只下日语那张，不牵动另外 10 张。 */
const LOADERS: Record<string, () => Promise<Record<string, string>>> = {
  ja: () => import('./ja.ts').then((module) => module.ja),
  ko: () => import('./ko.ts').then((module) => module.ko),
  ru: () => import('./ru.ts').then((module) => module.ru),
  de: () => import('./de.ts').then((module) => module.de),
  fr: () => import('./fr.ts').then((module) => module.fr),
  es: () => import('./es.ts').then((module) => module.es),
  pt: () => import('./pt.ts').then((module) => module.pt),
  hi: () => import('./hi.ts').then((module) => module.hi),
  ar: () => import('./ar.ts').then((module) => module.ar),
  bn: () => import('./bn.ts').then((module) => module.bn),
  id: () => import('./id.ts').then((module) => module.id),
};

/**
 * localStorage 键 —— **与 `lib/http.ts` 的 `K_LANG` 是同一个键**（那边不导出，故此处
 * 重写一遍字面量）。刻意**不新开第二个键**：界面语言与实际发出的 `X-Language` 必须是
 * 同一份真值，两个键就是两处会漂的副本（漂了的症状是「界面切了、服务端文案没变」）。
 *
 * ⚠ 这条「同键」不是靠注释保证的，`i18n.test.ts` 有一条行为级钉子：`setCode('ja')` 之后
 * `language.get() === 'ja'`（`language` 就是 `lib/http.ts` 里那个发头的对象）。
 */
export const LOCALE_KEY = 'gp_language';

/**
 * 把语言落到 DOM 上：`lang`（无障碍朗读、拼写检查）与 `dir`（书写方向）。
 *
 * 为什么必须有这一步：**文案翻了但 `dir` 还是 ltr，阿拉伯语界面就是从左往右排的** ——
 * 文本方向、标点位置、flex 的起点全反。
 */
function applyDocumentLocale(code: string): void {
  if (typeof document === 'undefined') return; // node --test 无 DOM
  const root = document.documentElement;
  root.lang = code;
  root.dir = isRtl(code) ? 'rtl' : 'ltr';
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

/** 只增不减的修订号 —— 订阅侧拿它当快照（见 `notify`）。 */
let revision = 0;

/**
 * 通知订阅者重绘。切语言与**表到货**都走这一个口子 ⇒ 订阅者不可能只收到其中一种。
 *
 * 为什么订阅的不是「当前语言」本身：表是异步到的。切到日语时 `current` 已经是 `ja`，
 * 等译文拉回来再通知一次是**同值**，`useSyncExternalStore` 比对快照认为没变、不重绘，
 * 界面就永远停在原语言上。
 */
function notify(): void {
  revision += 1;
  for (const listener of listeners) listener();
}

/** 已在飞的加载（按语言去重，同一语言不重复发请求）。失败时删掉，下次切回来再试。 */
const loading = new Map<string, Promise<void>>();

/**
 * 把某种语言的表拉进运行期。**不抛**：拉不到就当英文用（`en` 永远是逐键兜底），
 * 界面照常可用，下次切语言再试。
 *
 * 调用方**不必等**：到货后 `notify()` 标脏重绘（先显示英文，再换成该语言）。
 */
export function ensure(code: string): Promise<void> {
  const target = resolve(code).code;
  const load = LOADERS[target];
  if (!load || TABLES[target]) return Promise.resolve();
  const pending = loading.get(target);
  if (pending) return pending;
  const task = load().then(
    (table) => {
      // 存**引用**不是拷贝：用例改表对象后 `t()` 要看得见（i18n.test.ts 的单键缺失回落）
      TABLES[target] = table;
      notify();
    },
    () => {
      loading.delete(target);
    },
  );
  loading.set(target, task);
  return task;
}

// 首屏就拉偏好里那一种：存着 ja 时不能等用户再切一次才显示日语（不阻塞渲染，到货即重绘）
void ensure(current);

/**
 * **没有 DOM 时**（`node --test`）把 11 张懒表在模块求值期就地拉齐。
 *
 * 为什么非这样不可：那几条用例全是**同步**断言 —— `TABLES` 必须在本模块求值完就齐
 * （键集断言、「t() 查的是本语言的表」、以及「删一个键看是否逐键回落 en」）。异步到货满足不了。
 *
 * 判别式用 `typeof document`：它在两边都成立、打包器也不会折叠它。浏览器里这段整块跳过
 * （await 根本不执行），11 张表只经 `ensure()` 的懒 chunk 到货。
 */
if (typeof document === 'undefined') {
  await Promise.all(Object.keys(LOADERS).map((code) => ensure(code)));
}

/**
 * 当前语言短码 —— **给需要它的非 React 模块读**（组件一律用 `useI18n()`）。
 *
 * 用模块级变量而不是让调用方去拿 React 上下文：请求可以在任何组件、乃至组件树之外发起，
 * 而取不到上下文时抛异常会把请求打死。与 flutter 用静态字段 `LocaleController.currentCode`
 * 是同一个理由，同一处失败模式。
 */
export function currentCode(): string {
  return current;
}

/** 切语言：归一 → 落值（含 localStorage）→ 通知订阅者。认不出来的码落 `DEFAULT_CODE`。 */
export function setCode(code: string): void {
  current = resolve(code).code;
  applyDocumentLocale(current);
  try {
    // 与 lib/http.ts 的 language.get() 同键：切完界面，出站请求头自动跟着变
    localStorage.setItem(LOCALE_KEY, current);
  } catch {
    // 隐私模式 / 无 DOM：切这一次仍生效，只是下次进来回到默认值
  }
  notify();
  // 该语言的表可能还没到货（10 种非 en/zh）：这里只管踢一脚，界面先按 en 兜底渲染，
  // 到货后 `ensure` 自己再 `notify()` 一次 —— 不必也不该在这里 await
  void ensure(current);
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

/** 快照取值器：必须是稳定引用、且不随渲染变（返回数字，`useSyncExternalStore` 不会误判成每次都在变）。 */
export const revisionOf = (): number => revision;

export function subscribe(listener: () => void): () => void {
  listeners.add(listener);
  return () => listeners.delete(listener);
}
