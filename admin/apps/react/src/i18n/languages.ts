/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
/**
 * 支持的语言清单 —— **与两棵 flutter 的 `LocaleController.supported` 逐项同码同名**，
 * 真值面在后端 `packages/platform-common/src/Locale.php` 的 `SUPPORTED`（13 种，
 * 与 `install/lang/*.php` 对齐）。后端增删语言时这里必须同步。
 *
 * 为什么是本地镜像而不是从后端拉：这是**设置菜单**，离线/未登录/后端不可达时也要能渲染，
 * 拉不到就整块菜单空掉是不能接受的。
 */

/** 一个受支持的语言：短码 + 母语名。 */
export type Language = {
  /** **短码**，两个用途：存进 localStorage、以及作为 `X-Language` 发给后端
   * （后端 `common\Locale::normalize()` 认短码与 `zh-CN` 全码两种写法）。 */
  code: string;
  /** 母语名。**照抄别译**：用户看不懂当前界面语言时也要能选对自己那一项。 */
  native: string;
};

/** 顺序即切换器里的显示顺序。 */
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

/** 首次进入 / 认不出来的码一律落这里，也是翻译查不到时的回落语言。 */
export const DEFAULT_CODE = 'en';

/** 任意码 → 受支持的语言；认不出来回落 `DEFAULT_CODE`（`LANGUAGES[0]`，与 flutter 的 `resolve` 同款）。
 * 不抛错：偏好里存着后端已下线的语言码时，界面该照常起来而不是白屏。 */
export function resolve(code: string | null | undefined): Language {
  return LANGUAGES.find((item) => item.code === code) ?? LANGUAGES[0];
}
