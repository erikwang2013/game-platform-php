/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { Api } from '../api.service';
import { DICT } from './dictionary';
import { I18n, colKey, isRtl, t, use } from './i18n';
import { LANGS, normalize } from './langs';
import { LOCALES } from './locales';

describe('i18n 机制', () => {
  // 这里**不**统一 use() 定语言：各用例要哪种自己显式切（模块级真值会跨用例留存）
  beforeEach(() => {
    TestBed.resetTestingModule();
  });

  afterEach(() => {
    localStorage.clear();
    TestBed.resetTestingModule();
  });

  /** 刷新页面后语言还在 —— 也就是「模块加载时从偏好还原」这条路径真的接了线 */
  it('启动时从偏好还原当前语言（重新加载模块视为刷新页面）', async () => {
    localStorage.setItem('ga_lang', 'zh');
    vi.resetModules();
    const fresh = await import('./i18n');
    expect({ lang: fresh.lang(), dash: fresh.t('nav.dashboard') }).toEqual({
      lang: 'zh',
      dash: '仪表盘',
    });
  });

  it('13 种语言：短码与母语名逐字对照（与两棵 flutter 同一份清单，改一处就是改三棵树）', () => {
    expect(LANGS.map((l) => `${l.code}:${l.native}`)).toEqual([
      'en:English',
      'zh:简体中文',
      'ja:日本語',
      'ko:한국어',
      'ru:Русский',
      'de:Deutsch',
      'fr:Français',
      'es:Español',
      'pt:Português',
      'hi:हिन्दी',
      'ar:العربية',
      'bn:বাংলা',
      'id:Bahasa Indonesia',
    ]);
  });

  it('全码/大写/乱码一律归一到小写短码，认不出的回落 en', () => {
    expect(normalize('zh-CN')).toBe('zh');
    expect(normalize('zh_CN')).toBe('zh');
    expect(normalize('ZH')).toBe('zh');
    expect(normalize('pt-BR')).toBe('pt');
    expect(normalize('ko')).toBe('ko');
    // 认不出的一律 en：这条同时是「没存过偏好」的初值口径
    expect(normalize('xx')).toBe('en');
    expect(normalize('')).toBe('en');
    expect(normalize(null)).toBe('en');
    expect(normalize(undefined)).toBe('en');
  });

  /**
   * 用户报过「显示可切 13 种，实际只有中英」—— 根因就是下面这 11 种语言**根本没建表**。
   * 断言用的是整句文案（任何语言的译文都不会与英文逐字相同），所以这一条同时钉两件事：
   * 表建出来了、且 `t()` 真的在查它（而不是查了英文表）。
   *
   * 全量完整性（键集/占位符/是否照抄英文）在文末的 `13 种语言的译文完整性` 那组里。
   */
  it('其余 11 种语言各有自己的表：切到日文查到的就是日文', async () => {
    use('en');
    expect(t('nav.dashboard')).toBe('Dashboard');
    for (const l of LANGS.filter((x) => x.code !== 'en')) {
      await use(l.code); // 这 11 种的表是懒加载的，断言前要等它就绪（见 i18n.ts 的 ensure）
      expect(TestBed.inject(I18n).lang()).toBe(l.code);
      expect({ lang: l.code, rendered: t('login.captcha_prompt') }).not.toEqual({
        lang: l.code,
        rendered: 'Click the image as prompted',
      });
    }
    use('zh');
    expect(t('nav.dashboard')).toBe('仪表盘');
    use('en'); // 别把语言留给下一条用例
  });

  it('键查不到时原样返回（未抽取的字面量照常显示）；占位符是 {name} 且漏传不吞', () => {
    expect(t('这个键没登记')).toBe('这个键没登记');
    expect(t('')).toBe('');
    use('zh');
    expect(t('app.pager', { page: 2, pages: 5, total: 96 })).toBe('共 96 条 · 第 2/5 页');
    // 漏传的占位符原样留着：宁可显示 {total}，也别静默拼出一句少了数字的文案
    expect(t('app.pager', { page: 2 })).toBe('共 {total} 条 · 第 2/{pages} 页');
    use('en');
    expect(t('app.pager', { page: 2, pages: 5, total: 96 })).toBe('Page 2 / 5 (96 total)');
  });

  it('切换即持久化：偏好写的是小写短码，界面与偏好同刻改', () => {
    use('zh-CN');
    expect(localStorage.getItem('ga_lang')).toBe('zh');
    expect(TestBed.inject(I18n).lang()).toBe('zh');
    expect(t('nav.dashboard')).toBe('仪表盘');
    use('日语');
    expect(localStorage.getItem('ga_lang')).toBe('en'); // 认不出 ⇒ 归一成 en，不写脏值
  });

  /**
   * 本任务最硬的一条：**语言必须真的发到后端**。后端 `LanguageMiddleware` 按 `X-Language`
   * 选翻译表，头不发 ⇒ 界面切成英文时服务端 message 仍是中文，「支持 13 种」只落一半。
   * 断言的是出站 `TestRequest` 上的真实请求头，不是「代码里写了这行」。
   */
  it('X-Language 挂在每个出站请求上：切换后下一个请求立刻变，且不挤掉 Authorization', async () => {
    TestBed.configureTestingModule({
      providers: [provideRouter([]), provideHttpClient(), provideHttpClientTesting()],
    });
    const http = TestBed.inject(HttpTestingController);
    const api = TestBed.inject(Api);
    localStorage.setItem('ga_access_token', 'T0KEN');
    use('en'); // 起点显式：第一个请求断的就是 en（不受前一条用例留下的语言影响）

    const path = (r: { url: string }): string => r.url.split('?')[0]!;

    const first = api.get('/admin/v1/dashboard');
    const req1 = http.expectOne((r) => path(r) === '/admin/v1/dashboard');
    expect(req1.request.headers.get('X-Language')).toBe('en');
    // 加了语言头不能把认证头挤掉（两行 headers 一起发）
    expect(req1.request.headers.get('Authorization')).toBe('Bearer T0KEN');
    req1.flush({ code: 0, message: 'ok', data: {} });
    await first;

    use('ja');
    const second = api.get('/admin/v1/dashboard');
    const req2 = http.expectOne((r) => path(r) === '/admin/v1/dashboard');
    expect(req2.request.headers.get('X-Language')).toBe('ja');
    req2.flush({ code: 0, message: 'ok', data: {} });
    await second;

    // 未登录也要带头：语言与登录态无关。
    // logout 现在**会打到服务端**（POST /profile/logout，服务端吊销令牌）——先满足它再断下面那条，
    // 否则这里断的就不是「未登录」，而是一个 token 还在的中间态。
    const out = api.logout();
    http
      .expectOne((r) => path(r) === '/admin/v1/profile/logout')
      .flush({ code: 0, message: 'ok', data: {} });
    await out;
    expect(localStorage.getItem('ga_access_token')).toBeNull();

    const third = api.get('/admin/v1/dashboard');
    const req3 = http.expectOne((r) => path(r) === '/admin/v1/dashboard');
    expect(req3.request.headers.get('X-Language')).toBe('ja');
    expect(req3.request.headers.has('Authorization')).toBe(false);
    req3.flush({ code: 0, message: 'ok', data: {} });
    await third;
    http.verify();
  });

  /** 本地兜底文案（服务端没给 message 时才用）也必须过查表 —— 写死中文就是「切了语言还是中文」 */
  it('兜底文案跟着语言走：切到日文时抛的是日文译文，不再是英文', async () => {
    TestBed.configureTestingModule({
      providers: [provideRouter([]), provideHttpClient(), provideHttpClientTesting()],
    });
    const http = TestBed.inject(HttpTestingController);
    const api = TestBed.inject(Api);
    await use('ja');

    // 先把「日文表真的建出来了」钉死：否则下面那条断言在整片回落英文时也会通过
    const expected = t('app.request_failed', { code: 500 });
    expect(expected).not.toBe('Request failed (500)');

    const p = api.get('/admin/v1/dashboard');
    http
      .expectOne((r) => r.url.split('?')[0] === '/admin/v1/dashboard')
      .flush({ code: 500, message: '', data: null });
    await expect(p).rejects.toThrow(expected);
    use('en');
  });
});

/**
 * 表格**列标题**的兜底（用户报过「模块的列表标题没做多语言」）。
 *
 * 修复前的链路：各模块页面不传 `heads`，`ui-table::head()` 退回 `t(字段名)` —— 字段名不是键，
 * `t()` 原样返回 ⇒ 任何语言下列头都是 `real_name` 这种裸字段名。真机实测 en/zh 两列现已随语言变。
 */
describe('列标题兜底键 colKey', () => {
  it('接口字段名能查到 col.<字段名>', () => {
    expect(colKey('username')).toBe('col.username');
    expect(colKey('created_at')).toBe('col.created_at');
  });

  it('表里没有的字段名返回 null（调用方据此退回字段名本身，不拼凭空键）', () => {
    expect(colKey('definitely_not_a_field')).toBeNull();
  });

  it('col.* 的两种语言都非空且不同（zh 不是照抄 en）', () => {
    use('en');
    const en = t('col.username');
    use('zh');
    const zh = t('col.username');
    expect(en).toBe('Username');
    expect(zh).toBe('用户名');
    expect(en).not.toBe(zh);
  });

});

/**
 * 用户报过「语言菜单能切 13 种，界面实际只有中英」—— 根因是另外 11 种**就没建表**（本批已补）。
 * 这组防的是反向退化：表建了，但**缺条目**、**丢占位符**、或**整列照抄英文** ——
 * 三种在界面上都是静默的（缺条目回落英文，看起来只是「这一页没翻译」）。
 */
describe('13 种语言的译文完整性', () => {
  /** 该串里的占位符集合（排序去重后可直接比字符串） */
  const ph = (s: string): string => [...new Set(s.match(/\{\w+\}/g) ?? [])].sort().join(',');
  const enOf = (k: string): string => (DICT[k] ? DICT[k][0] : k);
  /** en/zh 的表就是 DICT 本身，不在这三条的范围内 */
  const others = LANGS.filter((l) => l.code !== 'en' && l.code !== 'zh');

  // 标题里的数字**现算**：这处原来硬编码「719」，到本轮实测已是 823（上一批加键时没人改标题），
  // 而它只活在标题里、从不参与断言 —— 硬编码的计数在这个位置只会长期说谎。真正的断言是下面
  // 那段键集相等（locales 多一个键，locales/index.ts 当场就抛）。
  it(`键集两两相等：每种语言都覆盖全部 ${Object.keys(DICT).length} 键，一条不漏（多出来的键在 locales/index.ts 就抛了）`, () => {
    const keys = Object.keys(DICT);
    const bad: string[] = [];
    for (const l of others) {
      const got = new Set(Object.keys(LOCALES[l.code]!));
      const missing = keys.filter((k) => !got.has(k));
      if (missing.length) bad.push(`${l.code} 缺 ${missing.length} 条，例如 ${missing.slice(0, 3)}`);
    }
    expect(bad).toEqual([]);
  });

  it('占位符逐键对齐：漏一个 {name}，界面上就是硬编码的 {name}', () => {
    const bad: string[] = [];
    for (const l of others) {
      for (const [k, v] of Object.entries(LOCALES[l.code]!)) {
        const want = ph(enOf(k));
        if (ph(v) !== want) bad.push(`${l.code} ${k}: 期望 [${want}] 实得 [${ph(v)}]`);
      }
    }
    expect(bad.slice(0, 10)).toEqual([]);
  });

  /**
   * 本任务最容易出的假交付：表建了、键齐了、占位符也在，**但整列是英文的复制品**。
   * 少量逐字相同属正常（JSON 示例、专有名词、`\n`、只剩占位符的格式串 —— 实测每种语言 9~41 条），
   * 20% 的闸门只会在「整列复制」时才响。
   */
  it('每一列都不是英文的复制品（与英文逐字相同的键 < 20%）', () => {
    const bad: string[] = [];
    for (const l of others) {
      const table = LOCALES[l.code]!;
      const keys = Object.keys(table);
      const same = keys.filter((k) => table[k] === enOf(k)).length;
      if (same / keys.length >= 0.2) bad.push(`${l.code}: ${same}/${keys.length}`);
    }
    expect(bad).toEqual([]);
  });
});

/**
 * 语言要落到 DOM 上（`lang` + `dir`）——只翻文案不设 `dir`，**阿拉伯语界面仍是 LTR 排版**。
 *
 * 本树有 jsdom ⇒ 可以直接读 `document.documentElement`，是**行为级**断言（不用读源码）。
 * react 树同款已真机验过；这条是它的仓内对应物。
 */
describe('书写方向落到 documentElement', () => {
  afterEach(() => {
    document.documentElement.dir = '';
    document.documentElement.lang = '';
  });

  it('切到阿拉伯语 → dir=rtl 且 lang=ar', async () => {
    await use('ar');
    expect(document.documentElement.dir).toBe('rtl');
    expect(document.documentElement.lang).toBe('ar');
  });

  it('切回日语 → dir 回到 ltr（不残留 rtl）', async () => {
    await use('ar');
    await use('ja');
    expect(document.documentElement.dir).toBe('ltr');
    expect(document.documentElement.lang).toBe('ja');
  });

  it('中文是 ltr —— 别把"非拉丁"一律当 RTL', async () => {
    await use('zh');
    expect(document.documentElement.dir).toBe('ltr');
  });

  it('13 种里恰好只有阿拉伯语是 RTL', () => {
    expect(isRtl('ar')).toBe(true);
    expect(LANGS.filter((l) => isRtl(l.code)).map((l) => l.code)).toEqual(['ar']);
  });

  it('认不出来的码按 ltr 处理（不误镜像）', () => {
    expect(isRtl('klingon')).toBe(false);
  });
});
