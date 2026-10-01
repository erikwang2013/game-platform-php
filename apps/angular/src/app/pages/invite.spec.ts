/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { InvitePage } from './invite';

type Sig<T> = { (): T; set(v: T): void };
type Probe = {
  code: Sig<string | null>;
  busy: Sig<boolean>;
  err: Sig<string>;
  link(): string;
  generate(): void;
};

/**
 * 邀请好友（分享短码）的契约。
 *
 * 钉四件：
 *  ① `generate()` 打的是 `POST /api/v1/shares`，**不带 activity_id**（那是可选的关联活动）；
 *  ② 链接形状 = 本树自己的挂载点 + `login?code=`，且**用 document.baseURI** 拼
 *     —— 写死 `/` 会让子路径部署（`--base=/app/`）的链接指到别的应用上去；
 *  ③ 生成的码**不缓存**（服务端没有读端点、也没有"我的短码"列表 ⇒ 缓存就是第二真值源）；
 *  ④ 失败把服务端 message 原样透出（不是"生成失败"）。
 */
describe('InvitePage 邀请好友', () => {
  let http: HttpTestingController;
  let page: InvitePage;

  const probe = (): Probe => page as unknown as Probe;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting(), provideRouter([])],
    });
    http = TestBed.inject(HttpTestingController);
    page = TestBed.runInInjectionContext(() => new InvitePage());
  });

  afterEach(() => {
    try {
      http.verify();
    } finally {
      TestBed.resetTestingModule();
    }
  });

  const created = (code: string) => ({
    code: 0,
    message: 'Created',
    data: { short_code: code, expires_at: null },
  });

  it('没有码时链为空（不摆一个拼不出来的链接）', () => {
    expect(probe().code()).toBeNull();
    expect(probe().link()).toBe('');
    http.expectNone(() => true);
  });

  it('生成：POST /api/v1/shares，不带 activity_id；成功后摆出码与链接', () => {
    probe().generate();
    const req = http.expectOne('/api/v1/shares');
    expect(req.request.method).toBe('POST');
    expect('activity_id' in (req.request.body as Record<string, unknown>)).toBe(false);
    req.flush(created('AbC12345'));

    expect(probe().code()).toBe('AbC12345');
    expect(probe().busy()).toBe(false);
    // 链接用 document.baseURI 拼（jsdom 下是 http://localhost/）⇒ 与挂载点同源，
    // 子路径部署时也指得对，而不是写死根路径
    expect(probe().link()).toBe(`${new URL('login', document.baseURI).href}?code=AbC12345`);
  });

  it('再生成一个：旧码被新码替掉（服务端查不回旧码，页面也**不缓存**）', () => {
    probe().generate();
    http.expectOne('/api/v1/shares').flush(created('AAAAAAAA'));
    probe().generate();
    http.expectOne('/api/v1/shares').flush(created('BBBBBBBB'));

    expect(probe().code()).toBe('BBBBBBBB');
    expect(probe().link()).toContain('code=BBBBBBBB');
  });

  it('生成中重复点只发一次（busy 门）', () => {
    probe().generate();
    probe().generate();
    http.expectOne('/api/v1/shares').flush(created('AbC12345'));
    http.expectNone('/api/v1/shares');
  });

  it('失败：服务端 message 原样透出，且**不留下半个码**', () => {
    probe().generate();
    // 用非 401 的码：401 会被 ApiBase 走「清令牌 + 跳登录」，那是传输层的用例，
    // 这里要钉的是「失败文案不被吞成『生成失败』」
    http.expectOne('/api/v1/shares').flush({ code: 500, message: '服务器忙，请稍后重试', data: [] });

    expect(probe().err()).toBe('服务器忙，请稍后重试');
    expect(probe().code()).toBeNull();
    expect(probe().busy()).toBe(false);
  });
});
