/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { Api } from '../api.service';
import { I18n, colKey, t, use } from './i18n';
import { LANGS, normalize } from './langs';

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

  it('其余 11 种语言不建表，查表回落英文（只有 en/zh 两张表）', () => {
    use('en');
    expect(t('nav.dashboard')).toBe('Dashboard');
    use('ja');
    expect(TestBed.inject(I18n).lang()).toBe('ja');
    expect(t('nav.dashboard')).toBe('Dashboard'); // 日文没有自己的表 ⇒ 英文
    use('zh');
    expect(t('nav.dashboard')).toBe('仪表盘');
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

    // 未登录也要带头：语言与登录态无关
    api.logout();
    const third = api.get('/admin/v1/dashboard');
    const req3 = http.expectOne((r) => path(r) === '/admin/v1/dashboard');
    expect(req3.request.headers.get('X-Language')).toBe('ja');
    expect(req3.request.headers.has('Authorization')).toBe(false);
    req3.flush({ code: 0, message: 'ok', data: {} });
    await third;
    http.verify();
  });

  /** 本地兜底文案（服务端没给 message 时才用）也必须过查表 —— 写死中文就是「切了语言还是中文」 */
  it('兜底文案跟着语言走：切到日文（无表 ⇒ 回落英文）时是 Request failed（500）', async () => {
    TestBed.configureTestingModule({
      providers: [provideRouter([]), provideHttpClient(), provideHttpClientTesting()],
    });
    const http = TestBed.inject(HttpTestingController);
    const api = TestBed.inject(Api);
    use('ja');

    const p = api.get('/admin/v1/dashboard');
    http
      .expectOne((r) => r.url.split('?')[0] === '/admin/v1/dashboard')
      .flush({ code: 500, message: '', data: null });
    await expect(p).rejects.toThrow('Request failed (500)');
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
