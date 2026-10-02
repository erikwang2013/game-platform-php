/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 查表入口 —— 与 `admin/apps/harmonyos/entry/src/main/ets/i18n/Translations.ets` 同构。
 *
 * **13 张全量表与 `LocaleController.SUPPORTED_LOCALES` 一一对应**（短码：
 * zh/en/ja/ko/ru/de/fr/es/pt/hi/ar/bn/id，真值面是后端 `common\Locale::SUPPORTED`）。
 *
 * ⚠ **`buildTables()` 里漏登记一行 = 该语言静默回落英文** —— 菜单能选中、界面却是英文，
 * 不报错、不崩溃，可以一直挂着没人发现（本仓四棵树都踩过这一族）。加语言**顺序不可颠倒**：
 * 先建同键集的 `Translations<Code>.ets`，再在这里登记。
 *
 * ⚠ 这两件事**编译器都不管**（少一张表、表里少一个键，都只是界面上多一串英文），
 * 本仓也没有常驻脚本拦它 ⇒ 加/改完语言，**编译前自己核一遍**：`TABLES` 的键集 ≡
 * `SUPPORTED_LOCALES` 的 code 集、每张表键集 ≡ `TranslationsEn.ets` 的键集。
 *
 * 键是 `域.名`；占位符写 `{name}`，用 `t1()` 或 `.replace()` 填。
 */

import { EN } from './TranslationsEn';
import { ZH } from './TranslationsZh';
import { JA } from './TranslationsJa';
import { KO } from './TranslationsKo';
import { RU } from './TranslationsRu';
import { DE } from './TranslationsDe';
import { FR } from './TranslationsFr';
import { ES } from './TranslationsEs';
import { PT } from './TranslationsPt';
import { HI } from './TranslationsHi';
import { AR } from './TranslationsAr';
import { BN } from './TranslationsBn';
import { IDN } from './TranslationsId';
import { LocaleController } from './LocaleController';

/**
 * 语言短码 → 全量表。**13 项，与 `SUPPORTED_LOCALES` 同键**。
 *
 * 写成 builder 函数而不是顶层对象字面量：ArkTS 禁非空对象字面量
 * （`arkts-no-untyped-obj-literals`），顶层下标赋值语句同样有风险 —— 函数体里做最稳，
 * 与 `TranslationsZh.ets` 的 `buildZh()` 同款。
 */
function buildTables(): Record<string, Record<string, string>> {
  const tables: Record<string, Record<string, string>> = {};
  tables['zh'] = ZH;
  tables['en'] = EN;
  tables['ja'] = JA;
  tables['ko'] = KO;
  tables['ru'] = RU;
  tables['de'] = DE;
  tables['fr'] = FR;
  tables['es'] = ES;
  tables['pt'] = PT;
  tables['hi'] = HI;
  tables['ar'] = AR;
  tables['bn'] = BN;
  tables['id'] = IDN;
  return tables;
}

const TABLES: Record<string, Record<string, string>> = buildTables();

/** 表里没有的码回落 `en`（`EN` 同时兜住「表里没这个键」）。 */
function tableFor(code: string): Record<string, string> {
  const hit = TABLES[code];
  return hit !== undefined ? hit : EN;
}

/**
 * 查表。
 *
 * 回落链：当前语言的表 → `EN` → **键名本身**。最后一步是刻意的：
 * 漏译在界面上显示成 `profile.title` 这样的键名，一眼能看出是漏的，
 * 比回一条空白（以为控件没画出来）好定位。
 */
export function t(key: string): string {
  const hit = tableFor(LocaleController.currentCode)[key];
  if (hit !== undefined) {
    return hit;
  }
  const fallback = EN[key];
  return fallback !== undefined ? fallback : key;
}

/** 带一个占位符的查表：表里写 `{name}`，这里换成值 */
export function t1(key: string, name: string, value: string): string {
  return t(key).replace(`{${name}}`, value);
}
