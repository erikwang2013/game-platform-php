/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Injectable, Pipe, PipeTransform, signal } from '@angular/core';
import { DICT } from './dictionary';
import { FALLBACK, LANGS, normalize } from './langs';

/** 偏好键：与 `ga_access_token`/`ga_user` 同前缀（auth.service.ts 读的也是 localStorage） */
const KEY = 'ga_lang';

/**
 * 语言码 → 查表。en/zh 来自 `[en, zh]` 二元组；其余 11 种由 `ensure()` 按需从 `locales/` 拉。
 *
 * 拉回来的表**建在英文表之上**（`{ ...en, ...自己的译文 }`）：缺一条译文只是回落成英文，
 * 而不是显示成键名。正常情况下缺不了（`i18n.spec.ts` 有键集相等的常驻断言），
 * 这条覆盖是给「以后往 `dict/` 加了键、还没补译文」那段时间兜底的。
 */
const TABLES: Record<string, Record<string, string>> = { en: {}, zh: {} };
for (const [key, pair] of Object.entries(DICT)) {
  TABLES['en']![key] = pair[0];
  TABLES['zh']![key] = pair[1];
}

/** 读偏好；隐私模式/无 localStorage 时回落 en（不抛） */
function saved(): string {
  try {
    return normalize(localStorage.getItem(KEY));
  } catch {
    return FALLBACK;
  }
}

/**
 * 当前语言 —— **模块级信号是唯一真值**：DI 服务、模板管道、以及非 DI 的自由函数
 * （util/render/upload 那些纯函数）读的都是它，于是「界面」与「出站请求头」不可能各说各话。
 */
const LANG = signal(saved());

/** 13 种里只有阿拉伯语是 RTL（bn / hi 都是 LTR —— 别把"非拉丁文字"一律当 RTL）。 */
const RTL_CODES = new Set(['ar']);

/** 该语言是否从右往左。导出给用例钉「13 种里恰好只有 ar 是 RTL」。 */
export function isRtl(code: string): boolean {
  return RTL_CODES.has(normalize(code));
}

/**
 * 把语言落到 DOM 上：`lang`（无障碍朗读、拼写检查）与 `dir`（书写方向）。
 *
 * 为什么必须有：**文案翻了但 `dir` 还是 ltr，阿拉伯语界面就是从左往右排的** ——
 * 文本方向、标点位置、flex/grid 起点全反。翻译解决"字对不对"，这一步解决"排得对不对"。
 * react 树同款（那边已真机验过：选 العربية → `dir=rtl`，切回日本語 → 回 `ltr`）。
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
 * signal 不通知，界面就永远停在英文上（正是用户报的那个症状的另一种形态）。
 * 所以另开一个只增不减的信号，`t()` 读它一次，加载完成后 +1 即标脏。
 */
const VERSION = signal(0);

/**
 * 11 种语言的译文**不在首屏包里**：全量内联会让 main chunk 从 303 kB 涨到 902 kB
 * （gzip 92 kB → 208 kB），顶破 `angular.json` 的 500 kB 预算（离 1 MB 硬失败只剩 75 kB）。
 * 而这个应用绝大多数人用 en/zh（已在包里），11 种语言里每人只会用到一种 —— 没理由让所有人下载。
 *
 * 首次切到非 en/zh 时拉一次（同一个 promise 复用，不重复发请求）；**拉不到就当英文用**，
 * 不影响任何其它功能，下次切语言再试。
 */
let loading: Promise<void> | null = null;
function ensure(code: string): Promise<void> {
  if (TABLES[code]) return Promise.resolve();
  if (!loading) {
    loading = import('./locales')
      .then(({ LOCALES }) => {
        for (const [c, table] of Object.entries(LOCALES)) TABLES[c] = { ...TABLES[FALLBACK]!, ...table };
        VERSION.update((v) => v + 1);
      })
      .catch(() => {
        loading = null; // 回落英文；下次切语言再试
      });
  }
  return loading;
}

// 启动时如果存的偏好就是这 11 种之一，先把表拉起来（不阻塞渲染：到货后 VERSION 标脏重绘）
void ensure(LANG());

/**
 * 查表。**认不出的键原样返回** —— 两用：未抽取的字面量照常显示（迁移期不炸），
 * 以及键名本身就是最后兜底。缺键回落英文（11 种语言的表都建在英文表之上）。
 * 占位符是 `{name}`（与 flutter 一致）；`params` 里没有的占位符原样留着，便于发现漏传。
 */
export function t(key: string, params?: Record<string, unknown>): string {
  void VERSION(); // 懒加载到货后，模板里跑过的查表要重跑一遍
  const table = TABLES[LANG()] ?? TABLES[FALLBACK]!;
  const s = table[key] ?? TABLES[FALLBACK]![key] ?? key;
  if (!params) return s;
  return s.replace(/\{(\w+)\}/g, (m, k: string) => (k in params ? String(params[k]) : m));
}

/**
 * 表格**列标题**的兜底键：接口字段名 → `col.<字段名>`，词条表里没有则返回 null。
 *
 * 为什么需要它：各模块页面**不传 `heads`**（只有 admins.ts 传），`ui-table::head()` 于是退回
 * `t(字段名)` —— 字段名不是键 ⇒ 任何语言下列头都显示 `real_name` 这种裸字段名。
 * 用 `key in DICT` 现查而不是维护映射表：以后往 `dict/columns.ts` 加一条会被自动吃到。
 */
export function colKey(name: string): string | null {
  const key = `col.${name}`;
  return key in DICT ? key : null;
}

/** 当前语言短码（模块级读法，自由函数与用例用；DI 侧是 `I18n.lang` 信号） */
export function lang(): string {
  return LANG();
}

/**
 * 切语言 + 持久化。认不出的码在这一步就归一成 en，存进去的永远是小写短码。
 *
 * 返回的 promise 在「该语言的表已就绪」时 resolve（en/zh 是同步 resolve）——
 * 调用方**不需要等**（界面会先渲染英文，到货后自动重绘）；用例需要确定性的那一刻才 `await`。
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
 * 与两棵 flutter 同构的三件事：
 *  1. **13 种平铺**（母语名、当前项打点）由 langs.ts 驱动，增删语言只改那一处；
 *  2. 切一次语言同时生效三处：信号（界面重绘）、`X-Language`（服务端 message 跟着变）、
 *     localStorage（下次进来还记得）；
 *  3. 13 种语言各有自己的表（en/zh 来自 `dict/*.ts`，其余首屏后按需从 `locales/` 拉）。
 */
@Injectable({ providedIn: 'root' })
export class I18n {
  /** 13 种语言（母语名），语言菜单直接遍历它 */
  readonly langs = LANGS;

  /**
   * 当前语言 —— **界面与出站请求头读的都是它这一个真值**：模板里的 `t` 管道读它（切语言即重绘），
   * `Api` 发请求时读它挂 `X-Language`（见 api.service.ts 的 sendRaw）。
   *
   * flutter 那边是「静态码 + 响应式码」两份，因为 Dio 的拦截器拿不到 Controller；
   * 本树的真值面是模块级信号，DI 服务只是门面 —— 一份状态，没有会走散的第二处。
   */
  readonly lang = LANG.asReadonly();

  /** 查表（自由函数版见模块级 `t()`） */
  readonly t = t;

  /** 切语言 + 持久化（返回值见 `use()` 的说明：界面不必等它） */
  readonly use = use;
}

/**
 * 模板用的查表管道：`{{ 'nav.users' | t }}`、`{{ 'crud.create' | t: { name: n } }}`。
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
