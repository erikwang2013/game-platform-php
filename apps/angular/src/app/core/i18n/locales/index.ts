/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { DICT } from '../dictionary';
import { AR } from './ar';
import { BN } from './bn';
import { DE } from './de';
import { ES } from './es';
import { FR } from './fr';
import { HI } from './hi';
import { ID } from './id';
import { JA } from './ja';
import { KO } from './ko';
import { PT } from './pt';
import { RU } from './ru';

/**
 * 11 种语言的译文（en/zh 不在这里 —— 它们是 `dict/*.ts` 里 `[en, zh]` 二元组的第 0/1 位）。
 *
 * **为什么 en/zh 不做成第 12、13 个文件**：那两张表是**原文与默认语言的同一声明**
 * （`[en, zh]` 二元组一行两值，改文案时两列贴着改不容易走散），拆出去会让「加一条词条」
 * 变成改两个地方。11 种语言是纯译文，生命周期不同（原文随功能改、译文随译者补）。
 *
 * **为什么一种语言一个文件**：全表 ~530 键时单文件会顶破 500 行上限 —— 到那时按
 * `dictionary.ts` 的域边界再拆成 `<code>-1.ts` / `<code>-2.ts`（管理端树就是这么做的）。
 * 当前规模（外壳域）远未到，先不预拆。
 */
const PARTS: Record<string, Record<string, string>> = {
  ja: JA,
  ko: KO,
  ru: RU,
  de: DE,
  fr: FR,
  es: ES,
  pt: PT,
  hi: HI,
  ar: AR,
  bn: BN,
  id: ID,
};

/**
 * 语言码 → `键: 译文`。守卫**当场抛**，不静默（与 `dictionary.ts` 的「重复键直接抛」同一条纪律
 * —— 这类错在界面上全是静默的，只是某个地方文案不对，看不出异常）：
 * 译文里出现 `DICT` 里没有的键（拼错的键永远查不到，还会让人以为「已经译了」）。
 *
 * 同表内重复键不在此列：JS 对象字面量里重复键在**解析期**就只剩后者，运行时已经数不出，
 * 而 `tsc` 对字面量重复键直接报错（TS1117）—— 有编译门在，不必再写一条永远不触发的检查。
 *
 * `i18n.spec.ts` 另有「13 种语言键集两两相等」的常驻断言，防的是反向的「漏译」。
 */
export const LOCALES: Record<string, Record<string, string>> = {};
for (const [code, table] of Object.entries(PARTS)) {
  for (const key of Object.keys(table)) {
    if (!(key in DICT)) throw new Error(`i18n 译文有未知键：${code} ${key}`);
  }
  LOCALES[code] = table;
}
