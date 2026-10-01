/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
/**
 * i18n 运行时：当前语言（模块级，供请求层读）+ 查表 + 占位符 + 订阅。
 * 与两棵 flutter 的 `LocaleController`/`AppTranslations` 同构，差别只在 React 侧的绑定方式。
 *
 * 13 种语言**各一张全量表**（与 `languages.ts` 的 `LANGUAGES` 逐项对应），
 * `t` 在 `TABLES[code]` 里查不到某个键才走 `en`，再查不到回键名本身
 * （与 flutter 的 `_data[locale]?[key] ?? _data['en']?[key] ?? key` 逐级同款）。
 *
 * ⚠ **首屏只带 en，其余 12 张按需拉**（`ensure`）。全量内联会让首屏多背约 739 kB
 * （gzip 约 223 kB，实测 12 个懒 chunk 之和），而**每个人只会用到一种语言** ——
 * 让所有人下载 13 种是没有理由的。angular 树同款（那边 en/zh 静态、11 种在懒 chunk 里）。
 */
import { useSyncExternalStore } from 'react';
import { en, type MessageKey } from './en.ts';
import { DEFAULT_CODE, resolve } from './languages.ts';

export { LANGUAGES, DEFAULT_CODE, resolve } from './languages.ts';
export type { Language } from './languages.ts';
export type { MessageKey } from './en.ts';

/**
 * 语言表集 —— **它同时就是运行期查表用的注册表**：首屏只有 `en`（`t` 的逐键兜底也是它），
 * 其余 12 张在 `ensure()` 到货时逐个补进来。
 *
 * ⚠ **应用代码不许引用本导出**：运行期查表一律走 `t()`。导出它只为 `node --test` 的两类断言 ——
 * 结构断言（「切换器列了 13 种、却有一种没注册表」是**静默**故障，只有「每种都真有表 + 键集与
 * en 逐项相同」抓得住），以及那条「删掉一个键看是否逐键回落 en」的用例（它得改到 `t()` 正读着的
 * 那个对象才作数，所以这里存的是**引用**不是拷贝）。
 */
export const TABLES: Record<string, Record<string, string>> = { en };

/** 语言码 → 该语言的表。**一种语言一个 chunk**：切到日语只下日语那张，不牵动另外 11 张。 */
const LOADERS: Record<string, () => Promise<Record<string, string>>> = {
  zh: () => import('./zh.ts').then((module) => module.zh),
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

/** 只增不减的修订号 —— 订阅侧拿它当快照（见 `notify`）。 */
let revision = 0;

/**
 * 通知订阅者重绘。切语言与**表到货**都走这一个口子 ⇒ 订阅者不可能只收到其中一种。
 *
 * 为什么订阅的不是「当前语言」本身：表是异步到的。切到日语时 `current` 已经是 `ja`，
 * 等译文拉回来再通知一次是**同值**，`useSyncExternalStore` 比对快照认为没变、不重绘，
 * 界面就永远停在英文上（angular 树为此单开了 VERSION 信号，同一个理由、同一处失败模式）。
 */
function notify(): void {
  revision += 1;
  for (const listener of listeners) listener();
}

/** 已在飞的加载（按语言去重，同一语言不重复发请求）。失败时删掉，下次切回来再试。 */
const loading = new Map<string, Promise<void>>();

/**
 * 把某种语言的表拉进运行期。**不抛**：拉不到就当英文用（`en` 永远是逐键兜底），
 * 界面照常可用，下次切语言再试 —— 与 angular 的 `ensure()` 同款。
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
 * **没有 DOM 时**（`node --test`）把 12 张表在模块求值期就地拉齐。
 *
 * 为什么非这样不可：那三个用例文件全是**同步**断言 —— `TABLES` 必须在本模块求值完就齐
 * （键集断言、「t() 查的是本语言的表」、以及「删一个键看是否逐键回落 en」）。异步到货满足不了。
 *
 * 为什么不能改回「13 个静态 import」：静态 import 是**求值前**就建好的边，只要它在，那 12 张表
 * 就必然被留在首屏 chunk 里。给注册语句加 PURE 注解确实能把 `TABLES` 与注册调用整块摇掉（实测摇掉了），
 * 但**模块本身仍被那条边拖着** —— `ja.ts` 的 `{...jaUi, ...jaFields}` 是跨模块展开，打包器证不了它
 * 无副作用，于是 `LOADERS` 的懒加载被就地内联。实测首屏：带那 12 条静态边时 `1,069.66 kB /
 * 323.47 kB gzip`，去掉之后是 `222.11 kB / 69.44 kB gzip`。
 *
 * 判别式用 `typeof document`：它在两边都成立、打包器也不会折叠它。浏览器里这段整块跳过（await
 * 根本不执行），12 张表只经 `ensure()` 的懒 chunk 到货。代价是本模块成为 async 模块，引用方多等
 * 一个微任务 —— 这是为了让「测试要同步、首屏要按需」两个约束同时成立所付的**全部**代价。
 */
if (typeof document === 'undefined') {
  await Promise.all(Object.keys(LOADERS).map((code) => ensure(code)));
}

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
  notify();
  // 该语言的表可能还没到货（11 种非 en）：这里只管踢一脚，界面先按 en 兜底渲染，
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
const revisionOf = (): number => revision;

function subscribe(listener: () => void): () => void {
  listeners.add(listener);
  return () => listeners.delete(listener);
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
  // 订阅修订号（不是一个语言码字符串）：表异步到货时语言码没变，靠它才能把页面重绘一遍
  useSyncExternalStore(subscribe, revisionOf);
  // 修订号变了就重渲染，这里现读当前语言 —— 与 `t()` 读的是同一份模块级真值，不可能各说各话
  return { code: current, setCode, t };
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
