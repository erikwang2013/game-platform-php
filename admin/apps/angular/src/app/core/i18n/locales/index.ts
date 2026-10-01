/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { DICT } from '../dictionary';
import { AR_1 } from './ar-1';
import { AR_2 } from './ar-2';
import { BN_1 } from './bn-1';
import { BN_2 } from './bn-2';
import { DE_1 } from './de-1';
import { DE_2 } from './de-2';
import { ES_1 } from './es-1';
import { ES_2 } from './es-2';
import { FR_1 } from './fr-1';
import { FR_2 } from './fr-2';
import { HI_1 } from './hi-1';
import { HI_2 } from './hi-2';
import { ID_1 } from './id-1';
import { ID_2 } from './id-2';
import { JA_1 } from './ja-1';
import { JA_2 } from './ja-2';
import { KO_1 } from './ko-1';
import { KO_2 } from './ko-2';
import { PT_1 } from './pt-1';
import { PT_2 } from './pt-2';
import { RU_1 } from './ru-1';
import { RU_2 } from './ru-2';

/**
 * 11 种语言的译文（en/zh 不在这里 —— 它们是 `dict/*.ts` 里 `[en, zh]` 二元组的第 0/1 位）。
 *
 * **为什么不把 `[en, zh]` 扩成 13 元组**：那 14 个 dict 文件会各涨约 6.5 倍（`risk.ts` 已 245 行
 * → 约 1600 行），当场顶破 500 行上限；且原文与译文是两种生命周期（原文随功能改、译文随译者补），
 * 拆开才不会「改一句中文文案」把 11 列译文全搅进同一个 diff。
 *
 * **为什么一种语言两个文件**：719 键一行一条 ≈ 730 行，单文件超 500 行。切口落在 `dictionary.ts`
 * 的域边界上（part1 = frame…support 共 10 域，part2 = settings…columns 共 5 域），一个域的词条
 * 不会跨文件。往某个域加新键时，照该域所在的那一份加。
 */
const PARTS: Record<string, [Record<string, string>, Record<string, string>]> = {
  ko: [KO_1, KO_2],
  ru: [RU_1, RU_2],
  de: [DE_1, DE_2],
  fr: [FR_1, FR_2],
  es: [ES_1, ES_2],
  pt: [PT_1, PT_2],
  hi: [HI_1, HI_2],
  ar: [AR_1, AR_2],
  bn: [BN_1, BN_2],
  id: [ID_1, ID_2],
  ja: [JA_1, JA_2],
};

/**
 * 语言码 → `键: 译文`。两道守卫都**当场抛**，不静默（与 `dictionary.ts` 的「重复键直接抛」同一条纪律
 * —— 这两类错在界面上全是静默的，只是某个地方文案不对，看不出异常）：
 *  1. 同一语言两块里出现同名键（后一块会悄悄顶掉前一块）；
 *  2. 译文里出现 `DICT` 里没有的键（拼错的键永远查不到，还会让人以为「已经译了」）。
 * `i18n.spec.ts` 另有「13 种语言键集两两相等」的常驻断言，防的是反向的「漏译」。
 */
export const LOCALES: Record<string, Record<string, string>> = {};
for (const [code, parts] of Object.entries(PARTS)) {
  const table: Record<string, string> = {};
  for (const part of parts) {
    for (const [key, text] of Object.entries(part)) {
      if (key in table) throw new Error(`i18n 译文重复：${code} ${key}`);
      if (!(key in DICT)) throw new Error(`i18n 译文有未知键：${code} ${key}`);
      table[key] = text;
    }
  }
  LOCALES[code] = table;
}
