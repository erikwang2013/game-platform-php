/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */

/** 一个受支持的语言：短码 + 母语名 */
export interface Lang {
  /**
   * 短码。两个用途：存 localStorage（`ga_lang`）、以及作为 `X-Language` 发给后端
   * （后端 `common\Locale::normalize()` 认短码与 `zh-CN` 全码两种写法，这里统一小写短码）。
   */
  code: string;
  /** 母语名：语言菜单用它 —— 用户看不懂当前界面语言时也得能选对 */
  native: string;
}

/**
 * 支持的语言 —— **镜像后端 `common\Locale::SUPPORTED`（13 种，与 `install/lang/*.php` 对齐）**，
 * 与 `admin/apps/flutter` 的 `LocaleController.supported`、`apps/flutter/platform` 同一份清单，
 * 母语名逐字相同（改这里就是改三棵树，别只改一棵）。
 *
 * 为什么是本地镜像而不是从后端拉：这是**设置菜单**，离线/未登录/后端不可达时也要能渲染，
 * 拉不到就整块菜单空掉是不能接受的。代价是这份清单成了第二处副本 ——
 * 真值面在后端（`TranslationService::getAvailableLanguages()`），后端增删语言时这里必须同步。
 */
export const LANGS: Lang[] = [
  { code: 'en', native: 'English' },
  { code: 'zh', native: '简体中文' },
  { code: 'ja', native: '日本語' },
  { code: 'ko', native: '한국어' },
  { code: 'ru', native: 'Русский' },
  { code: 'de', native: 'Deutsch' },
  { code: 'fr', native: 'Français' },
  { code: 'es', native: 'Español' },
  { code: 'pt', native: 'Português' },
  { code: 'hi', native: 'हिन्दी' },
  { code: 'ar', native: 'العربية' },
  { code: 'bn', native: 'বাংলা' },
  { code: 'id', native: 'Bahasa Indonesia' },
];

/** 认不出来的码一律回落它（也是没有偏好时的初值，与两棵 flutter 的 `currentCode = 'en'` 一致） */
export const FALLBACK = 'en';

/**
 * 归一到短码：`zh-CN` / `zh_CN` / `ZH` 都认成 `zh`；不在 13 种里的（含 null/空）回落 `en`。
 * 与后端 `Locale::normalize()` 同口径 —— 后端对语言头大小写不敏感，但前端只发小写短码。
 */
export function normalize(code: unknown): string {
  const short = String(code ?? '')
    .trim()
    .toLowerCase()
    .split(/[-_]/)[0];
  return LANGS.some((l) => l.code === short) ? String(short) : FALLBACK;
}
