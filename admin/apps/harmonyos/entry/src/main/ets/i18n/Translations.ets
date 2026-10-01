/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

/**
 * 查表入口 —— 与 `admin/apps/flutter/lib/app/i18n/translations.dart` 的 `AppTranslations.t()` 同构。
 *
 * 与 flutter 兄弟树的差异只有一处：**这里只有 en / zh 两张全量表**，
 * 其余 11 种语言（ja/ko/ru/de/fr/es/pt/hi/ar/bn/id）不建表 —— 切过去界面走英文。
 * 这是已拍板路径的已知代价：13 种平铺的**选择器**是完整的，**表**暂时只有两张。
 * 补语言 = 加一张同形状的表 + 在 `tableFor()` 里加一个分支，别处不用动。
 *
 * 键是 `域.名`；占位符写 `{name}`，用 `t1()` 或 `.replace()` 填。
 */

import { EN } from './TranslationsEn';
import { ZH } from './TranslationsZh';
import { LocaleController } from './LocaleController';

/**
 * 语言短码 → 全量表。表里没有的码回落 `en`（`EN` 同时兜住「表里没这个键」）。
 * 写成函数而不是顶层 `Record<string, Record<string,string>>` 常量：ArkTS 禁非空对象字面量，
 * 而模块顶层的下标赋值语句同样有风险 —— 函数体里做最稳。
 */
function tableFor(code: string): Record<string, string> {
  return code === 'zh' ? ZH : EN;
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
