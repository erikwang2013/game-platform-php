/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 查表入口 —— 与 `admin/apps/flutter/lib/app/i18n/translations.dart` 的 `AppTranslations.t()` 同构。
 *
 * **13 种语言各有全量表**（en/zh/ja/ko/ru/de/fr/es/pt/hi/ar/bn/id），与 `LocaleController.SUPPORTED_LOCALES`
 * 一一对应。此前只有 en/zh 两张、其余 11 种切过去回落英文 —— 那是当时的已知代价，现已补齐。
 *
 * 补语言 = 加一张同形状的表（`Translations<Code>.ets`）+ 在 `buildTables()` 里加一行，别处不用动。
 * 加完必须验两件事：**键集与 `TranslationsEn.ets` 逐键相同**（键表真值在 en），以及**真编译器过**
 * （codelinter 对 ArkTS 限制规则零灵敏，它的 `Errors: 0` 不能当证据）。
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
import { ID } from './TranslationsId';
import { LocaleController } from './LocaleController';

/**
 * 语言短码 → 全量表。键必须与 `LocaleController.SUPPORTED_LOCALES` 的 `code` 一一对应
 * （那边是设置菜单渲染用的清单，这边是查表用的，两处漏一处就会出现「能选中但界面走英文」）。
 *
 * 写成 builder 函数而不是顶层 `Record<string, Record<string,string>>` 字面量：ArkTS 禁非空对象字面量
 * （`arkts-no-untyped-obj-literals`），顶层下标赋值语句同样有风险 —— 函数体里做最稳，
 * 与 `TranslationsEn.ets` 的 `buildEn()` 同款。
 */
function buildTables(): Record<string, Record<string, string>> {
  const tables: Record<string, Record<string, string>> = {};
  tables['en'] = EN;
  tables['zh'] = ZH;
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
  tables['id'] = ID;
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
 *
 * ⚠ **调用它的页面必须挂 `@StorageLink(LOCALE_STORAGE_KEY)`**，否则切换语言时
 * `build()` 不会重跑（静态 `currentCode` 不是可观测状态）。见 `LocaleController` 的注释。
 */
export function t(key: string): string {
  const hit = tableFor(LocaleController.currentCode)[key];
  if (hit !== undefined) {
    return hit;
  }
  const fallback = EN[key];
  return fallback !== undefined ? fallback : key;
}

/** 带一个占位符的查表：表里写 `{name}`，这里换成值（与 flutter 的 `{name}` 写法一致） */
export function t1(key: string, name: string, value: string): string {
  return t(key).replace(`{${name}}`, value);
}
