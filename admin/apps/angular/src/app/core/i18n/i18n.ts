/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Injectable, Pipe, PipeTransform, signal } from '@angular/core';
import { DICT } from './dictionary';
import { FALLBACK, LANGS, normalize } from './langs';

/** 偏好键：与 `ga_access_token`/`ga_user` 同前缀（auth.service.ts 读的也是 localStorage） */
const KEY = 'ga_lang';

/** `[en, zh]` → 两张按语言码索引的表；**只有这两张**，其余 11 种查不到就回落英文 */
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

/**
 * 查表。**认不出的键原样返回** —— 两用：未抽取的字面量照常显示（迁移期不炸），
 * 以及键名本身就是最后兜底。缺键回落英文（其余 11 种语言没有自己的表）。
 * 占位符是 `{name}`（与 flutter 一致）；`params` 里没有的占位符原样留着，便于发现漏传。
 */
export function t(key: string, params?: Record<string, unknown>): string {
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

/** 切语言 + 持久化。认不出的码在这一步就归一成 en，存进去的永远是小写短码 */
export function use(code: string): void {
  const next = normalize(code);
  LANG.set(next);
  try {
    localStorage.setItem(KEY, next);
  } catch {
    /* 存不下（隐私模式）不影响本次会话生效 */
  }
}

/**
 * 语言控制器（DI 门面；状态在模块级 LANG 里，三处读的都是同一份）。
 *
 * 与两棵 flutter 同构的三件事：
 *  1. **13 种平铺**（母语名、当前项打点）由 langs.ts 驱动，增删语言只改那一处；
 *  2. 切一次语言同时生效三处：信号（界面重绘）、`X-Language`（服务端 message 跟着变）、
 *     localStorage（下次进来还记得）；
 *  3. 表只有 en/zh 两张，其余 11 种回落英文。
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

  /** 切语言 + 持久化 */
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
