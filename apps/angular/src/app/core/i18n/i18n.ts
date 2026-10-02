/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Injectable, Pipe, PipeTransform, signal } from '@angular/core';
import { DICT } from './dictionary';
import { FALLBACK, LANGS, normalize } from './langs';

/**
 * 偏好键 —— **与 `session.ts` 的 `K_LANG` 同一个键**（`gp_language`，也和 `apps/react` 逐字相同）。
 *
 * 这里只做读写，不 import `session.ts`：出站 `X-Language` 由 `session.ts` 的拦截器读**同一个键**，
 * 于是「切了界面语言」与「请求头跟着变」是同一件事，不可能各说各话
 * （本仓 flutter 树踩过「界面切了、请求头还是旧语言」，`frontend-locale-plumbing` 有记录）。
 */
const KEY = 'gp_language';

/**
 * 语言码 → 查表。en/zh 来自 `[en, zh]` 二元组；其余 11 种由 `ensure()` 按需从 `locales/` 拉。
 *
 * 拉回来的表**建在兜底表之上**（`{ ...zh, ...自己的译文 }`）：缺一条译文只是回落成中文，
 * 而不是显示成键名。正常情况下缺不了（`i18n.spec.ts` 有键集相等的常驻断言），
 * 这条覆盖是给「以后往 `dict/` 加了键、还没补译文」那段时间兜底的。
 */
const TABLES: Record<string, Record<string, string>> = { en: {}, zh: {} };
for (const [key, pair] of Object.entries(DICT)) {
  TABLES['en']![key] = pair[0];
  TABLES['zh']![key] = pair[1];
}

/** 读偏好；隐私模式/无 localStorage 时回落兜底（不抛） */
function saved(): string {
  try {
    return normalize(localStorage.getItem(KEY));
  } catch {
    return FALLBACK;
  }
}

/**
 * 当前语言 —— **模块级信号是唯一真值**：DI 服务、模板管道、以及非 DI 的自由函数
 * （`util`/`render`/`upload` 那些纯函数）读的都是它，于是「界面」与「出站请求头」不可能各说各话。
 */
const LANG = signal(saved());

/** 13 种里只有阿拉伯语是 RTL（bn / hi 都是 LTR —— 别把「非拉丁文字」一律当 RTL）。 */
const RTL_CODES = new Set(['ar']);

/** 该语言是否从右往左。导出给用例钉「13 种里恰好只有 ar 是 RTL」。 */
export function isRtl(code: string): boolean {
  return RTL_CODES.has(normalize(code));
}

/**
 * 把语言落到 DOM 上：`lang`（无障碍朗读、拼写检查）与 `dir`（书写方向）。
 *
 * 为什么必须有：**文案翻了但 `dir` 还是 ltr，阿拉伯语界面就是从左往右排的** ——
 * 文本方向、标点位置、flex/grid 起点全反。翻译解决「字对不对」，这一步解决「排得对不对」。
 * 管理端两棵树同款（react 那棵已真机验过：选 العربية → `dir=rtl`，切回日本語 → 回 `ltr`）。
 *
 * ⚠ 只有**写死 `left`/`right` 的样式**不会被 `dir` 自动镜像（浏览器只镜像逻辑方向）。
 * 本树样式用的是逻辑间距，暂不需要逐条改；将来加绝对定位浮层时要留意。
 */
function applyDocumentLocale(code: string): void {
  if (typeof document === 'undefined') return; // SSR / 非浏览器环境
  const root = document.documentElement;
  root.lang = code;
  root.dir = RTL_CODES.has(code) ? 'rtl' : 'ltr';
}

// 首屏就落：偏好里存的是 ar 时，不能等用户再切一次才镜像
applyDocumentLocale(LANG());

/**
 * 表版本号 —— 懒加载到货后用它触发重绘。
 *
 * 为什么不能只靠 `LANG`：切到 ja 时 `LANG` 已经是 `ja`，等译文拉回来再 `set('ja')` 是**同值**，
 * signal 不通知，界面就永远停在兜底语言上（正是用户报过的「能选中但走英文」的另一种形态）。
 * 所以另开一个只增不减的信号，`t()` 读它一次，加载完成后 +1 即标脏。
 */
const VERSION = signal(0);

/**
 * 语言码 → 该语言的表。**一种语言一个 chunk**：切到日语只下日语那张，不牵动另外 10 张。
 *
 * ⚠ **别改回 `import('./locales')`（barrel）**：那一次 `import()` 会把 11 张表打进同一个 chunk，
 * 实测 `chunk-*.js` 329710 字节 / gzip 60254，而单张只有 ~32KB / gzip ~9.9KB。
 * `apps/react` 的 `LOADERS` 是同形（那边 `ja-*.js` = 31920 / gzip 9902）。
 *
 * `locales/index.ts` 那个 barrel 现在**只剩 `i18n.spec.ts` 在用**（它要同步拿到全 11 张做键集比对），
 * 于是它持有的「译文有未知键当场抛」守卫落在测试期 —— 那本来就是它唯一能抓到东西的时机。
 */
const LOADERS: Record<string, () => Promise<Record<string, string>>> = {
  ja: () => import('./locales/ja').then((m) => m.JA),
  ko: () => import('./locales/ko').then((m) => m.KO),
  ru: () => import('./locales/ru').then((m) => m.RU),
  de: () => import('./locales/de').then((m) => m.DE),
  fr: () => import('./locales/fr').then((m) => m.FR),
  es: () => import('./locales/es').then((m) => m.ES),
  pt: () => import('./locales/pt').then((m) => m.PT),
  hi: () => import('./locales/hi').then((m) => m.HI),
  ar: () => import('./locales/ar').then((m) => m.AR),
  bn: () => import('./locales/bn').then((m) => m.BN),
  id: () => import('./locales/id').then((m) => m.ID),
};

/**
 * 11 种语言的译文**不在首屏包里**：本树绝大多数用户用 zh（默认）或 en，11 种语言里每人只会用到一种
 * —— 没理由让所有人下载。首次切到非 en/zh 时拉**那一种**（同一语言复用同一个 promise，不重复发请求）；
 * **拉不到就当兜底语言用**，不影响任何其它功能，下次切语言再试。
 */
const loading = new Map<string, Promise<void>>();
function ensure(code: string): Promise<void> {
  const load = LOADERS[code];
  if (!load || TABLES[code]) return Promise.resolve();
  const pending = loading.get(code);
  if (pending) return pending;
  const task = load().then(
    (table) => {
      TABLES[code] = { ...TABLES[FALLBACK]!, ...table };
      VERSION.update((v) => v + 1);
    },
    () => {
      loading.delete(code); // 回落兜底语言；下次切语言再试
    },
  );
  loading.set(code, task);
  return task;
}

// 启动时如果存的偏好就是这 11 种之一，先把表拉起来（不阻塞渲染：到货后 VERSION 标脏重绘）
void ensure(LANG());

/**
 * 查表。**认不出的键原样返回** —— 两用：未抽取的字面量照常显示（迁移期不炸），
 * 以及键名本身就是最后兜底。缺键回落兜底语言（11 种语言的表都建在 zh 表之上）。
 *
 * 占位符是 `{name}`：与两棵前端表同形态（`admin/apps/angular` 的 `dict/*`、本仓 flutter C 端的
 * `{var}` 替换）。⚠ **不是后端 `trans()` 的 `%name%`** —— 那是 PHP 侧 `resource/translations/` 的
 * 约定（键=英文句子），两者是不同层，别混用。`params` 里没有的占位符原样留着，便于发现漏传。
 */
export function t(key: string, params?: Record<string, unknown>): string {
  void VERSION(); // 懒加载到货后，模板里跑过的查表要重跑一遍
  const table = TABLES[LANG()] ?? TABLES[FALLBACK]!;
  const s = table[key] ?? TABLES[FALLBACK]![key] ?? key;
  if (!params) return s;
  return s.replace(/\{(\w+)\}/g, (m, k: string) => (k in params ? String(params[k]) : m));
}

/** 当前语言短码（模块级读法，自由函数与用例用；DI 侧是 `I18n.lang` 信号） */
export function lang(): string {
  return LANG();
}

/**
 * 切语言 + 持久化。认不出的码在这一步就归一成短码，存进去的永远是小写短码。
 *
 * 三处一起落值（缺一处就是「能选中但走英文」/「界面切了头没切」）：
 *  1. `LANG` 信号 —— 界面重绘 + `t()` 查表；
 *  2. `gp_language` —— **`session.ts` 的拦截器读它发 `X-Language`**，服务端 message 跟着变；
 *  3. `document` 的 `lang`/`dir` —— 无障碍与 RTL 排版。
 *
 * 返回的 promise 在「该语言的表已就绪」时 resolve（en/zh 是同步 resolve）——
 * 调用方**不需要等**（界面会先渲染兜底语言，到货后自动重绘）；用例需要确定性的那一刻才 `await`。
 */
export function use(code: string): Promise<void> {
  const next = normalize(code);
  LANG.set(next);
  applyDocumentLocale(next);
  try {
    localStorage.setItem(KEY, next);
  } catch {
    /* 存不下（隐私模式）不影响本次会话生效 */
  }
  return ensure(next);
}

/**
 * 语言控制器（DI 门面；状态在模块级 LANG 里，三处读的都是同一份）。
 *
 * 与 C 端 flutter 树的 `LocaleController` 同构的三件事：
 *  1. **13 种平铺**（母语名、当前项打点）由 `langs.ts` 驱动，增删语言只改那一处；
 *  2. 切一次语言同时生效三处（见 `use()`）；
 *  3. 13 种语言各有自己的表（en/zh 来自 `dict/*.ts`，其余首屏后按需从 `locales/` 拉）。
 */
@Injectable({ providedIn: 'root' })
export class I18n {
  /** 13 种语言（母语名），语言菜单直接遍历它 */
  readonly langs = LANGS;

  /** 当前语言 —— 模板里的 `t` 管道读它（切语言即重绘） */
  readonly lang = LANG.asReadonly();

  /** 查表（自由函数版见模块级 `t()`） */
  readonly t = t;

  /** 切语言 + 持久化（返回值见 `use()` 的说明：界面不必等它） */
  readonly use = use;
}

/**
 * 模板用的查表管道：`{{ 'nav.home' | t }}`、`{{ 'me.registered_at' | t: { date: d } }}`。
 *
 * **必须 `pure: false`**：纯管道按入参缓存，切语言时入参没变 ⇒ 拿到旧译文（界面不重绘）。
 * 非纯管道在模板求值里跑，读 LANG() 会被记成该视图的依赖，于是 set 之后视图标脏重跑。
 * 本应用每屏的查表调用在百次量级，就是一次 Map 取值，不值得再上缓存层。
 */
@Pipe({ name: 't', pure: false })
export class T implements PipeTransform {
  transform(key: string, params?: Record<string, unknown>): string {
    return t(key, params);
  }
}

/**
 * 存进 state 的文案 —— **两态**：词条键（+参数，渲染期才算）或**原文**（服务端给的，本地翻不了）。
 *
 * ⚠ 为什么不能存 `t()` 的**结果**：`t()` 在 **set 的那一刻**求值，存下来的是那一刻语言的字符串。
 * 之后切语言，模板里的 `| t` 会重绘、这一条不会 —— 屏幕上留着旧语言的残影。
 * **异步拉回来的文案必踩**：拉取发生在切语言之前、显示在之后，中间隔多久都可能。
 *
 * 形状与 C 端 `apps/react` 同族（那边 `Row.title` 存闭包、`Login.err` 存「原始错误 + 兜底键」）：
 * 原则都是**存原始 + 渲染期求值**，改的是求值时机不是数据。
 */
export type Msg = string | { key: string; params?: Record<string, unknown> };

/**
 * 模板用的文案管道：`{{ error() | mt }}`。
 *
 * 入参是**整个 `Msg`**（不是键），所以它与 `| t` 是两件事：`| t` 的入参是键，
 * `| mt` 的入参可能是键、也可能是服务端原文 —— 后者原样透出，不查表。
 *
 * **必须 `pure: false`**，理由同 `T`：纯管道切语言时不重跑。
 * 名字避开 `msg*`：本树 `me.ts` / `me-export.ts` 里都有叫 `msg` 的**成员**，
 * 方法式解析器会被模板作用域遮住（管道是独立命名空间，撞不上）。
 */
@Pipe({ name: 'mt', pure: false })
export class Mt implements PipeTransform {
  transform(v: Msg | null | undefined): string {
    if (v === null || v === undefined) return '';
    return typeof v === 'string' ? v : t(v.key, v.params);
  }
}
