/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import preferences from '@ohos.data.preferences';
import common from '@ohos.app.ability.common';

/**
 * 一个受支持的语言：短码 + 母语名。
 *
 * `code` 是**短码**，两个用途：存进偏好、以及作为 `X-Language` 发给后端
 * （后端 `common\Locale::normalize()` 认短码与 `zh-CN` 全码两种写法）。
 *
 * 用**显式 class**而不是对象字面量数组：ArkTS 禁 `arkts-no-untyped-obj-literals`，
 * 对象字面量不能作为类型/推断源（codelinter 对这条零灵敏，只有真编译器认）。
 */
export class AppLocale {
  code: string;
  nativeName: string;

  constructor(code: string, nativeName: string) {
    this.code = code;
    this.nativeName = nativeName;
  }
}

/**
 * 支持的语言 —— **镜像后端 `common\Locale::SUPPORTED`（13 种，与 `install/lang/*.php` 对齐）**。
 *
 * 为什么是本地镜像而不是从后端拉：语言要在**离线/未登录/后端不可达**时也能定，
 * 拉不到就整块菜单空掉是不能接受的。代价是这份清单成了第二处副本 —— 真值面在后端
 * （`TranslationService::getAvailableLanguages()`），**后端增删语言时这里必须同步**。
 *
 * 用母语名而不是译名 —— 语言菜单要在「用户还看不懂当前界面语言」时也能选对。
 *
 * ⚠ **列表首项 = `FALLBACK_CODE`**（`resolve()` 认不出来时回它）；本树是 C 端中文
 * App，所以要 **zh 在前**，不要照抄管理端那棵树（那边是 en 在前）。
 */
export const SUPPORTED_LOCALES: AppLocale[] = [
  new AppLocale('zh', '简体中文'),
  new AppLocale('en', 'English'),
  new AppLocale('ja', '日本語'),
  new AppLocale('ko', '한국어'),
  new AppLocale('ru', 'Русский'),
  new AppLocale('de', 'Deutsch'),
  new AppLocale('fr', 'Français'),
  new AppLocale('es', 'Español'),
  new AppLocale('pt', 'Português'),
  new AppLocale('hi', 'हिन्दी'),
  new AppLocale('ar', 'العربية'),
  new AppLocale('bn', 'বাংলা'),
  new AppLocale('id', 'Bahasa Indonesia')
];

const STORE_NAME: string = 'app_settings';
const PREF_KEY: string = 'app_locale';

/**
 * 兜底语言 —— **`'zh'`，不是 `'en'`**。
 *
 * 两件事共用这一个值：
 * ① 界面查表回落到哪张表；② 出站头 `X-Language` 的值（见 `ApiService`）。
 * ② 尤其不能填 en：服务端 `LanguageMiddleware::detectLocale()` 的第一步就是
 * `X-Language`，一旦恒发 `en` 就把 `config('translation.locale')` 的 **zh 默认
 * 主动压掉** —— 全中文界面里弹英文文案（`apps/react` 树踩过这个坑，2026-10-01 才修）。
 *
 * 不发这个头同样不行：那会掉到第二步 `Accept-Language`，浏览器/设备是法语时
 * 服务端吐法语而界面是中文 ⇒ **同屏两语言**。要「默认两边同语言」只能显式给 zh。
 */
export const FALLBACK_CODE: string = 'zh';

/**
 * AppStorage 键名 —— 组件用它订阅语言变化。
 *
 * **为什么用 AppStorage**：`t()` 读的是静态 `currentCode`，静态字段不是可观测状态，
 * ArkUI 不会因为它的变化重跑 `build()`。语言切换要重渲染，就得有一个**被观测的**
 * 值联动变化 —— 这正是 flutter 兄弟树「三处一起落值」里的响应式那一处，
 * 只是 ArkUI 的响应式原语是 AppStorage 而不是 Rx。
 *
 * ⚠ **写 `@StorageLink` 时这个常量用不上** —— 装饰器实参必须是**编译期字符串字面量**，
 * 传 `LOCALE_STORAGE_KEY` 编不过。照抄这一行（本树 14 处一致）：
 * `@StorageLink('app_locale') localeCode: string = 'zh';`
 * 默认值必须与 `FALLBACK_CODE` 一致（**`'zh'`，不是管理端那棵树的 `'en'`**）：键不存在时
 * 装饰器会拿默认值把它建出来，与 `t()` 用的兜底语言不一致就会「菜单打点在 en、界面却是 zh」。
 *
 * ⚠ **每个 build() 里调 `t()` 的 `@Component` 各挂一行**，不是只挂在切换入口那一页：
 * `BottomNav` 这类共享组件挂在每一页上，而 ArkUI 的局部更新**不保证**父组件重渲染会连带
 * 重跑子组件（各组件各自订阅）。本树现状 = 14 个渲染 `t()` 的组件各一行，形状照参照树
 * `admin/apps/harmonyos`（那边 20 页 + 共享组件同样逐个挂）。
 *
 * ⚠ 无设备/模拟器 ⇒ 这套只保证「编译过 + 声明在」。**刷新时机、以及 RTL 是否真镜像，
 * 本仓都没有运行时证据**。
 */
export const LOCALE_STORAGE_KEY: string = 'app_locale';

/**
 * 语言控制器 —— 与 `admin/apps/harmonyos/entry/src/main/ets/i18n/LocaleController.ets` 同构：
 * **静态 `currentCode`（请求头用）+ AppStorage（界面重渲染用）+ 偏好落盘（跨会话）三处一起落值**。
 *
 * 用静态字段而不是让 `ApiService` 去查 AppStorage：`request()` 是每个请求的必经之路，
 * 读一个静态字符串没有失败模式，也不必让网络层依赖 UI 层的存储。
 */
export class LocaleController {
  /** 当前语言短码 —— **给 `ApiService` 的请求头发 `X-Language` 读** */
  static currentCode: string = FALLBACK_CODE;

  /**
   * Application Context，由 `EntryAbility.onWindowStageCreate` 注入（那里才有）。
   * 为 null 时 `change()` 只落内存不落盘 —— 不让「拿不到 context」把切换动作本身打死。
   */
  private static ctx: common.Context | null = null;

  /** 认不出来的码回落列表首项（= `FALLBACK_CODE`） */
  static resolve(code: string): AppLocale {
    for (let i = 0; i < SUPPORTED_LOCALES.length; i++) {
      if (SUPPORTED_LOCALES[i].code === code) {
        return SUPPORTED_LOCALES[i];
      }
    }
    return SUPPORTED_LOCALES[0];
  }

  /**
   * 启动时调用一次：记住 context 并把落盘的语言读回来。
   *
   * **不抛异常**（内部全包）—— `EntryAbility` 用 `.then()` 挂 `loadContent`，
   * 一旦 reject 那个 then 不执行，界面就永远不加载。
   */
  static async init(ctx: common.Context): Promise<void> {
    LocaleController.ctx = ctx;
    try {
      const store = await preferences.getPreferences(ctx, STORE_NAME);
      const saved = (await store.get(PREF_KEY, '')) as string;
      if (saved.length > 0) {
        LocaleController.apply(saved);
      }
    } catch (_) {
      // 读不到就用默认语言，不影响启动
    }
  }

  /** 切换语言：先落值（界面立刻变），再落盘 */
  static async change(code: string): Promise<void> {
    LocaleController.apply(code);
    const ctx = LocaleController.ctx;
    if (ctx === null) {
      return;
    }
    try {
      const store = await preferences.getPreferences(ctx, STORE_NAME);
      await store.put(PREF_KEY, LocaleController.currentCode);
      await store.flush();
    } catch (_) {
      // 落盘失败不阻断切换：本次会话仍是新语言，只是下次启动回到旧值
    }
  }

  /** 落值的三处必须一起动：静态码（请求头）、AppStorage（界面）、currentCode 已归一 */
  private static apply(code: string): void {
    const locale = LocaleController.resolve(code);
    LocaleController.currentCode = locale.code;
    AppStorage.setOrCreate(LOCALE_STORAGE_KEY, locale.code);
    LocaleController.applySystemLanguage(locale.code);
  }

  /**
   * 把语言交给**系统**：`ApplicationContext.setLanguage()` 之后，ArkUI 会按该语言的书写方向排版。
   *
   * 为什么必须有：**文案翻了但没告诉系统，阿拉伯语界面仍是 LTR** —— 文本方向、标点位置、
   * flex/Row 的起点全反。翻译解决"字对不对"，这一句解决"排得对不对"。
   * （`setLanguage` 为 `@since 11`，本树 `compatibleSdkVersion 5.0.0(12)` ⇒ 可用。）
   *
   * `ctx === null`（`init` 还没跑到）时跳过，且**不让系统调用失败把切换动作打死**。
   *
   * ⚠ 这里只保证「调了、且编译得过」：**方向是否真的镜像需要设备/模拟器看**，本仓没有。
   */
  private static applySystemLanguage(code: string): void {
    if (LocaleController.ctx === null) {
      return;
    }
    try {
      LocaleController.ctx.getApplicationContext().setLanguage(code);
    } catch (_) {
      // 系统调用失败：本次界面文案仍是新语言，只是书写方向没跟着变
    }
  }
}
