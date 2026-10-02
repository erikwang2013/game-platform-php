/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, signal } from '@angular/core';
import { provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { App } from '../../app';
import { language } from '../session';
import { DICT } from './dictionary';
import { FALLBACK, LANGS, normalize } from './langs';
import { I18n, Mt, Msg, T, isRtl, lang, t, use } from './i18n';
import { LOCALES } from './locales';

/** en/zh 是 `dict/*.ts` 里 `[en, zh]` 二元组的两列，其余 11 种在 `locales/` */
const TABLE: Record<string, Record<string, string>> = {
  en: Object.fromEntries(Object.entries(DICT).map(([k, p]) => [k, p[0]])),
  zh: Object.fromEntries(Object.entries(DICT).map(([k, p]) => [k, p[1]])),
  ...LOCALES,
};

/** `{name}` 形态的占位符多重集（同一键里出现两次 `{x}` 也要数两次） */
const holes = (s: string): string[] => (s.match(/\{\w+\}/g) ?? []).sort();

/**
 * 界面语言层的钉子。
 *
 * 钉它的理由：**每一个「表缺条/键拼错/懒加载没到货」的缺口在界面上都是静默的** ——
 * 菜单照样能选中 13 种语言，只是那一种里有些字回落成中文，看不出异常
 * （用户报过的原话是「为何显示可切换其他语言，实际只有中文和英文？」）。
 * 仓内没有第二种观察者：`ng build` 不检查文案，真机脚本不进 CI。
 */
describe('i18n 表', () => {
  it('恰好 13 张表，键集与 en 两两相等', () => {
    expect(Object.keys(TABLE).sort()).toEqual([
      'ar', 'bn', 'de', 'en', 'es', 'fr', 'hi', 'id', 'ja', 'ko', 'pt', 'ru', 'zh',
    ]);
    const base = Object.keys(TABLE['en']!).sort();
    for (const [code, table] of Object.entries(TABLE)) {
      // 缺键 ⇒ 该语言下这条静默回落成中文（表建在 zh 之上），菜单里却仍显示该语言可用。
      // 断言写成「差集」而不是整个键集比较：后者 vitest 只打出 `…(1)`，**看不出少的是哪个键**。
      const missing = base.filter((k) => !(k in table));
      const extra = Object.keys(table).filter((k) => !base.includes(k));
      expect({ code, missing, extra }).toEqual({ code, missing: [], extra: [] });
    }
  });

  it('占位符逐键相等（形态是 {name}，不是后端的 %name%）', () => {
    const base = TABLE['en']!;
    for (const [code, table] of Object.entries(TABLE)) {
      for (const key of Object.keys(base)) {
        // 缺键由上面那条钉（它报得比这里准）；这条只管「键在但占位符写丢」
        if (!(key in table)) continue;
        // 少一个占位符 ⇒ 拼出来的句子里数字/名字直接消失，比译错更难发现
        expect({ code, key, holes: holes(table[key]!) }).toEqual({
          code,
          key,
          holes: holes(base[key]!),
        });
      }
    }
  });

  it('无空值', () => {
    for (const [code, table] of Object.entries(TABLE)) {
      for (const [key, text] of Object.entries(table)) {
        expect({ code, key, empty: text.trim() === '' }).toEqual({ code, key, empty: false });
      }
    }
  });
});

describe('langs 清单', () => {
  it('恰好 13 条，短码与后端的集合一致', () => {
    // 真值面在后端 `common\Locale::SUPPORTED`（键由它派生，见 TranslationService::getAvailableLanguages）
    expect(LANGS.map((l) => l.code)).toEqual([
      'en', 'zh', 'ja', 'ko', 'ru', 'de', 'fr', 'es', 'pt', 'hi', 'ar', 'bn', 'id',
    ]);
  });

  it('母语名与后端/两棵 flutter 逐字相同', () => {
    // 语言菜单的可用性前提就是「用户看不懂当前界面语言时也能选对」⇒ 这列不能是译名
    expect(LANGS.map((l) => l.native)).toEqual([
      'English', '简体中文', '日本語', '한국어', 'Русский', 'Deutsch', 'Français',
      'Español', 'Português', 'हिन्दी', 'العربية', 'বাংলা', 'Bahasa Indonesia',
    ]);
  });

  it('normalize：全码/大小写/下划线都归一，认不出的回落 FALLBACK', () => {
    expect(normalize('zh-CN')).toBe('zh');
    expect(normalize('ZH_CN')).toBe('zh');
    expect(normalize('ja-JP')).toBe('ja');
    expect(normalize('')).toBe(FALLBACK);
    expect(normalize(null)).toBe(FALLBACK);
    expect(normalize('xx')).toBe(FALLBACK);
  });

  /**
   * 单独一条钉常量本身 —— 上面那条只用 `FALLBACK` 当期望值，**把常量改错它照样绿**
   * （两边一起变），所以必须有这条字面量的。
   *
   * ⚠ 这条钉的是**本树契约**，不是「管理端树怎么写」：管理端 `admin/apps/angular` 是 `en`，
   * 本树必须是 `zh`。理由是三条（详见 `langs.ts` 的注释）：
   *  1. 抽取前本树界面文案全是中文 ⇒ 默认 en 等于凭 i18n 改掉所有老用户的界面；
   *  2. `session.ts` 的出站 `X-Language` 兜底已是 `zh` 且 `session.spec.ts` 钉着它
   *     ⇒ 默认 en 制造「界面英文 + 服务端中文文案」的错配；
   *  3. 与 `apps/react` 同键同兜底（`gp_language` + `|| 'zh'`），两棵树对同一用户发同一个头。
   *
   * 本会话今天刚为「恒发 `en` 把服务端 zh 默认压掉」修过这条链（`frontend-locale-plumbing`）
   * ⇒ 照搬管理端的 `en` 就是把刚修好的缺陷引回来。
   */
  it('兜底语言是 zh 字面量（不是管理端树的 en）', () => {
    expect(FALLBACK).toBe('zh');
  });

  it('13 种里恰好只有 ar 是 RTL（bn/hi 是非拉丁文字但都 LTR）', () => {
    expect(LANGS.filter((l) => isRtl(l.code)).map((l) => l.code)).toEqual(['ar']);
  });
});

describe('切语言', () => {
  beforeEach(() => {
    localStorage.clear();
    void use(FALLBACK);
  });

  it('use() 三处一起落值：信号 + gp_language + document.lang/dir', async () => {
    await use('ja');
    expect(lang()).toBe('ja');
    // 关键一处：拦截器就是读这个键发 X-Language ⇒ 界面切了服务端文案必须跟着切
    expect(localStorage.getItem('gp_language')).toBe('ja');
    expect(language.get()).toBe('ja');
    expect(document.documentElement.lang).toBe('ja');
    expect(document.documentElement.dir).toBe('ltr');

    await use('ar');
    expect(document.documentElement.dir).toBe('rtl');
    await use('en');
    // 切回去不能残留 rtl
    expect(document.documentElement.dir).toBe('ltr');
  });

  it('认不出的码落成 FALLBACK，而不是把偏好写成原值', () => {
    void use('xx-YY');
    expect(lang()).toBe(FALLBACK);
    expect(localStorage.getItem('gp_language')).toBe(FALLBACK);
  });

  /**
   * ⚠ 逐条走**全部 11 条 loader**，不是抽查两条。
   *
   * 理由：运行时已改成「一种语言一个 chunk」（`i18n.ts` 的 `LOADERS`），于是
   * **某一条 loader 的路径写错 / 具名导出写错，只有那一种语言静默回落成中文** ——
   * 菜单照样能选中它、其余 10 种全是绿的，界面上看不出任何异常。
   * 抽查两条只能保住被抽查的那两条（正是这条用例改前的形态）。
   *
   * 期望值取自 barrel（`LOCALES`），也就是「译文文件里实际写了什么」，
   * 与运行时那条 loader 是**两条独立路径** —— 只有两边都通才会相等。
   */
  it('11 条懒加载 loader 逐条到货（写错任一条＝那一种静默回落成中文）', async () => {
    for (const { code } of LANGS) {
      if (code === 'en' || code === 'zh') continue;
      await use(code);
      expect({ code, text: t('nav.home') }).toEqual({ code, text: TABLE[code]!['nav.home'] });
    }
  });

  it('未抽取的字面量原样显示（迁移期不炸）', () => {
    expect(t('还没抽的裸串')).toBe('还没抽的裸串');
  });
});

/** 只为让 `t` 管道进模板；不引整页壳 */
@Component({ selector: 'app-i18n-probe', imports: [T], template: `<b>{{ 'nav.home' | t }}</b>` })
class Probe {}

describe('t 管道', () => {
  beforeEach(() => {
    localStorage.clear();
    void use(FALLBACK);
  });

  afterEach(() => TestBed.resetTestingModule());

  it('切语言后模板文字真的变（非纯管道：纯管道会拿旧译文）', async () => {
    const fx = TestBed.configureTestingModule({ imports: [Probe] }).createComponent(Probe);
    fx.detectChanges();
    expect(fx.nativeElement.textContent).toContain('首页');

    await use('en');
    fx.detectChanges();
    expect(fx.nativeElement.textContent).toContain('Home');

    await use('ja');
    fx.detectChanges();
    expect(fx.nativeElement.textContent).toContain('ホーム');
  });
});

/** 只为让 `mt` 管道进模板；三个绑定分别盖住两种形态（键 / 原文）与参数填充、空值 */
@Component({
  selector: 'app-msg-probe',
  imports: [Mt],
  template: `<b>{{ keyed() | mt }}</b>|<i>{{ raw() | mt }}</i>|<u>{{ params() | mt }}</u>|<s>{{ empty() | mt }}</s>`,
})
class MsgProbe {
  readonly keyed = signal<Msg>({ key: 'nav.home' });
  readonly raw = signal<Msg>('服务端原文');
  readonly params = signal<Msg>({ key: 'withdraw.err_pending', params: { msg: 'boom' } });
  readonly empty = signal<Msg | null>(null);
}

describe('mt 管道（存进 state 的文案）', () => {
  beforeEach(() => {
    localStorage.clear();
    void use(FALLBACK);
  });

  afterEach(() => TestBed.resetTestingModule());

  it('键那一态跟着语言变、原文那一态原样透出；参数渲染期才填', async () => {
    const fx = TestBed.configureTestingModule({ imports: [MsgProbe] }).createComponent(MsgProbe);
    fx.detectChanges();
    const txt = (): string => fx.nativeElement.textContent as string;

    expect(txt()).toContain('首页'); // 键 → 查表
    expect(txt()).toContain('服务端原文'); // 原文 → 不查表
    expect(txt()).toContain('已有一笔提现处理中'); // 键 + 参数 → 渲染期填
    expect(txt()).toContain('boom');
    expect(txt()).not.toContain('withdraw.err_pending'); // 键名不许漏到界面上
    expect(txt().endsWith('|')).toBe(true); // 空值那一态渲染成空串，不崩

    // ⚠ 这一段是本管道存在的**全部理由**：入参对象**引用没变**，变的只有语言。
    // 纯管道按入参缓存 ⇒ 不重算 ⇒ 屏幕停在旧语言，正是「存进 state 的文案不跟着切」。
    await use('en');
    fx.detectChanges();
    expect(txt()).toContain('Home');
    expect(txt()).toContain('another withdrawal is already being processed');
    // 原文那一态**刻意不跟**：它是服务端按 `X-Language` 出的，本地翻不了
    expect(txt()).toContain('服务端原文');
  });
});

describe('外壳（真实 App 组件）', () => {
  beforeEach(() => {
    localStorage.clear();
    void use(FALLBACK);
  });

  afterEach(() => TestBed.resetTestingModule());

  /** 侧栏 5 个导航项渲染出来的文字 —— 这是壳里唯一能看出语言的可见部分 */
  const railText = (el: HTMLElement): string =>
    [...el.querySelectorAll('.rail .nav-item')].map((a) => a.textContent?.trim()).join('|');

  const render = () => {
    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting(), provideRouter([])],
    });
    const fx = TestBed.createComponent(App);
    fx.detectChanges();
    return fx;
  };

  it('侧栏与语言菜单渲染成当前语言，切过之后真的变', async () => {
    const fx = render();
    expect(railText(fx.nativeElement)).toBe('首页|游戏|钱包|消息|我的');

    await use('en');
    fx.detectChanges();
    expect(railText(fx.nativeElement)).toBe('Home|Games|Wallet|Messages|Me');

    // 语言菜单按钮上是**母语名**（不是当前语言的译名），13 项平铺
    await use('ja');
    fx.detectChanges();
    const btn = fx.nativeElement.querySelector('.lang-btn') as HTMLElement;
    expect(btn.textContent).toContain('日本語');
    expect(fx.nativeElement.querySelector('.lang-btn')?.getAttribute('aria-label')).toBe('言語');
  });

  it('语言菜单列出 13 项、当前项打点', async () => {
    const fx = render();
    const app = fx.componentInstance as unknown as { langOpen: { set(v: boolean): void } };
    app.langOpen.set(true);
    fx.detectChanges();

    const items = [...fx.nativeElement.querySelectorAll('.lang-menu button')] as HTMLElement[];
    expect(items.length).toBe(13);
    // 只剥当前项那个点，别顺手把内部空格也剥了（'Bahasa Indonesia' 会被剥成 'BahasaIndonesia'）
    expect(items.map((b) => (b.textContent ?? '').replace('●', '').trim()).join(',')).toBe(
      LANGS.map((l) => l.native).join(','),
    );
    // 当前语言那一项：打点（●）+ .on 类；其余项两者都没有
    const on = items.filter((b) => b.classList.contains('on'));
    expect(on.length).toBe(1);
    expect(on[0]!.textContent).toContain('●');
    expect(on[0]!.getAttribute('aria-checked')).toBe('true');
  });

  it('语言菜单按钮点了会开，点页面别处会关', () => {
    const fx = render();
    const app = fx.componentInstance as unknown as { langOpen: () => boolean };
    expect(app.langOpen()).toBe(false);

    const btn = fx.nativeElement.querySelector('.lang-btn') as HTMLButtonElement;
    btn.click();
    fx.detectChanges();
    expect(app.langOpen()).toBe(true);

    // closeLang 是 document 级监听：点顶栏以外的地方也要关（固定遮罩那条路被 backdrop-filter 破坏）
    document.body.click();
    fx.detectChanges();
    expect(app.langOpen()).toBe(false);
  });

  it('I18n 门面读的就是同一份状态（没有第二处真值）', async () => {
    TestBed.configureTestingModule({ providers: [provideHttpClient(), provideHttpClientTesting()] });
    const svc = TestBed.inject(I18n);
    await svc.use('ja');
    expect(svc.lang()).toBe('ja');
    expect(lang()).toBe('ja');
    expect(svc.t('nav.home')).toBe(t('nav.home'));
  });
});
