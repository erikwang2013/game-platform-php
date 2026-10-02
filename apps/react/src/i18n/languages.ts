/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
/**
 * 支持的语言清单 —— 短码 + 母语名。
 *
 * 真值面在后端 `packages/platform-common/src/Locale.php` 的 `SUPPORTED`（13 短码），
 * `TranslationService::getAvailableLanguages()` 由它派生（后者有钉子断言归一后与之相等）。
 * **后端增删语言时这里必须同步** —— 本文件是第三份副本（flutter 那棵是第二份）。
 *
 * 为什么是本地镜像而不是从 `/language/list` 拉：语言菜单要在离线/未登录/后端不可达时
 * 也能渲染，拉不到就整块菜单空掉不可接受；首屏渲染也不该先等一个网络往返。
 * 2026-10-02 核过：本清单与 `Locale::SUPPORTED` 的 13 条**逐项同码同名，无漂移**。
 */

/** 一个受支持的语言：短码 + 母语名。 */
export type Language = {
  /** **短码**，两个用途：存进 localStorage（键 `gp_language`）、以及作为 `X-Language` 发给后端
   * （后端 `common\Locale::normalize()` 认短码与 `zh-CN` 全码两种写法）。 */
  code: string;
  /** 母语名。**照抄别译**：用户看不懂当前界面语言时也要能选对自己那一项。 */
  native: string;
};

/** 顺序即切换器里的显示顺序 —— 与 C 端 flutter `LocaleController.supported` 逐项同序。 */
export const LANGUAGES: Language[] = [
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

/**
 * 没存过偏好时的默认语言，也是认不出来的码的落点。
 *
 * **必须是 `zh`**：本树 `lib/http.ts` 的 `language.get()` 兜底写的就是 `|| 'zh'`（:53），
 * 而界面语言与 `X-Language` **走同一个 localStorage 键**（见 `index.ts` 的 `LOCALE_KEY`）
 * ⇒ 两边同键同默认值，不可能各说各话。改成别的值会把服务端 `LanguageMiddleware` 的
 * 中文默认压掉（全中文界面里弹英文响应文案，那是一处已修的旧缺陷，别再打开）。
 *
 * ⚠ C 端 flutter 的默认是 `en`（`LocaleController.currentCode`），**与本树不同**：
 * 两棵树历史不同（本树生产代码恒发 `X-Language: zh`），别照抄。
 */
export const DEFAULT_CODE = 'zh';

/**
 * 任意码 → 受支持的语言；认不出来落 `DEFAULT_CODE`。
 *
 * 先按 `Locale::normalize()` 的口径取主语言子标签：`zh-CN` / `zh_CN` / `ZH` 一律认成 `zh`。
 * 这一手不是防御性编程 —— `language.set()` 的历史调用点（测试里）写的就是 `zh-CN` 全码，
 * 而本清单只收短码，不归一就会把 `zh-CN` 当成认不出来的码、静默落回默认值。
 *
 * 不抛错：偏好里存着后端已下线的语言码时，界面该照常起来而不是白屏。
 */
export function resolve(code: string | null | undefined): Language {
  const primary = String(code ?? '')
    .toLowerCase()
    .trim()
    .split(/[-_;,]/)[0];
  return LANGUAGES.find((item) => item.code === primary) ?? LANGUAGES.find((item) => item.code === DEFAULT_CODE)!;
}

/** 13 种里只有阿拉伯语是 RTL（bn/hi 等都是 LTR，别把「非拉丁」都想当然当 RTL）。 */
const RTL_CODES = new Set(['ar']);

/** 该语言是否从右往左。导出给用例钉「13 种里恰好只有 ar 是 RTL」。 */
export function isRtl(code: string): boolean {
  return RTL_CODES.has(resolve(code).code);
}
