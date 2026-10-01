/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { SecurityPage } from './security';

type Sig<T> = { (): T; set(v: T): void };
type Probe = {
  beginSetup(): void;
  cancelSetup(): void;
  enable(ev: Event): void;
  disable(ev: Event): void;
  enabled: Sig<boolean>;
  setup: Sig<{ secret: string; qr_url: string } | null>;
  codes: Sig<string[] | null>;
  code: Sig<string>;
  pwd: Sig<string>;
  offCode: Sig<string>;
  err: Sig<string>;
  offErr: Sig<string>;
  busy: Sig<boolean>;
  grouped: () => string;
};

const CODES = ['aB3xY9pQz1', 'cD4wZ8rTa2', 'eF5vA7sUb3', 'gH6uB6tVc4'];
const submit = (): Event => new Event('submit', { cancelable: true });

/**
 * 账号安全（2FA 自助）的钉子。
 *
 * 重点钉**两个方向不对称的 6 位边界**，因为「看着不一致就统一一下」正是这里最容易出的错：
 *  - 启用（/user/2fa/enable）与关闭（/user/2fa/disable）服务端 validator 都是 `size:6`
 *    （disable 只走 verifyTOTP，不查备份码表）⇒ 客户端必须只放 6 位；
 *  - 登录第二步（/2fa/verify）才是 `between:6,10`（10=备份码）⇒ 在 login.ts，不在这页。
 * 把这里也放宽到 10 会让用户拿备份码去点「启用」，而服务端**必定 422**。
 */
describe('SecurityPage 2FA 自助', () => {
  let http: HttpTestingController;
  let page: SecurityPage;

  const probe = (): Probe => page as unknown as Probe;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        provideRouter([{ path: '', children: [] }]),
      ],
    });
    http = TestBed.inject(HttpTestingController);
    // inject(Api) 写在字段初始化器里 ⇒ 必须在注入上下文里构造
    page = TestBed.runInInjectionContext(() => new SecurityPage());
    // 构造函数里的 status 请求先冲掉，后续用例各管各的
    http.expectOne('/api/v1/user/2fa/status').flush({ code: 0, message: 'ok', data: { enabled: false } });
  });

  afterEach(() => {
    try {
      http.verify();
    } finally {
      TestBed.resetTestingModule();
    }
  });

  it('status 回来即渲染开关状态，且 setup 前不显示密钥', () => {
    expect(probe().enabled()).toBe(false);
    expect(probe().setup()).toBeNull();
    expect(probe().grouped()).toBe('');
  });

  it('开始设置：POST /user/2fa/setup，密钥按 4 位分组显示', () => {
    probe().beginSetup();
    const req = http.expectOne('/api/v1/user/2fa/setup');
    expect(req.request.method).toBe('POST');
    expect(req.request.body).toBeNull();
    req.flush({
      code: 0,
      message: 'ok',
      data: { secret: 'ABCDEFGHIJKLMNOP', qr_url: 'otpauth://totp/x?secret=ABCDEFGHIJKLMNOP' },
    });
    expect(probe().setup()?.secret).toBe('ABCDEFGHIJKLMNOP');
    expect(probe().grouped()).toBe('ABCD EFGH IJKL MNOP');
    // 分组只是显示层，原始密钥（提交/复制用）必须不带空格
    expect(probe().setup()?.secret).not.toContain(' ');
    expect(probe().enabled()).toBe(false);
  });

  it('启用：请求体恰为 {code}，成功后拿到备份码且状态翻成已开启', () => {
    probe().beginSetup();
    http.expectOne('/api/v1/user/2fa/setup').flush({
      code: 0,
      message: 'ok',
      data: { secret: 'S', qr_url: 'otpauth://totp/x' },
    });

    probe().code.set('123456');
    probe().enable(submit());
    const req = http.expectOne('/api/v1/user/2fa/enable');
    expect(req.request.method).toBe('POST');
    expect(req.request.body).toEqual({ code: '123456' });
    req.flush({ code: 0, message: 'ok', data: { backup_codes: CODES } });

    expect(probe().codes()).toEqual(CODES);
    expect(probe().enabled()).toBe(true);
    // 密钥卡必须收掉，否则用户会对着一个已经启用的 setup 再点一次
    expect(probe().setup()).toBeNull();
    expect(probe().err()).toBe('');
  });

  it('启用：码错（422）留在第二步，密钥不丢、备份码不下发', () => {
    probe().beginSetup();
    http.expectOne('/api/v1/user/2fa/setup').flush({
      code: 0,
      message: 'ok',
      data: { secret: 'S', qr_url: 'otpauth://totp/x' },
    });
    probe().code.set('000000');
    probe().enable(submit());
    http
      .expectOne('/api/v1/user/2fa/enable')
      .flush({ code: 422, message: 'Invalid TOTP code', data: [] });

    expect(probe().setup()?.secret).toBe('S');
    expect(probe().codes()).toBeNull();
    expect(probe().enabled()).toBe(false);
    expect(probe().err()).toBe('Invalid TOTP code');
    expect(probe().busy()).toBe(false);
  });

  it('启用：7 位不发请求 —— 服务端 enable() 是 size:6，备份码在这里无效', () => {
    probe().beginSetup();
    http.expectOne('/api/v1/user/2fa/setup').flush({
      code: 0,
      message: 'ok',
      data: { secret: 'S', qr_url: 'otpauth://totp/x' },
    });
    probe().code.set('1234567');
    probe().enable(submit());
    http.expectNone('/api/v1/user/2fa/enable');
    expect(probe().setup()?.secret).toBe('S');
  });

  it('关闭：请求体恰为 {password, code}，成功后回到未开启', () => {
    probe().enabled.set(true);
    probe().pwd.set('secret123');
    probe().offCode.set('654321');
    probe().disable(submit());

    const req = http.expectOne('/api/v1/user/2fa/disable');
    expect(req.request.body).toEqual({ password: 'secret123', code: '654321' });
    req.flush({ code: 0, message: 'ok', data: [] });

    expect(probe().enabled()).toBe(false);
    expect(probe().pwd()).toBe('');
    expect(probe().offCode()).toBe('');
    expect(probe().offErr()).toBe('');
  });

  it('关闭：密码错（422）保持已开启并显示原因，不清空输入', () => {
    probe().enabled.set(true);
    probe().pwd.set('wrong');
    probe().offCode.set('654321');
    probe().disable(submit());
    http
      .expectOne('/api/v1/user/2fa/disable')
      .flush({ code: 422, message: 'Password is incorrect', data: [] });

    expect(probe().enabled()).toBe(true);
    expect(probe().offErr()).toBe('Password is incorrect');
    expect(probe().pwd()).toBe('wrong');
  });

  it('取消设置清空密钥与码，不发请求', () => {
    probe().beginSetup();
    http.expectOne('/api/v1/user/2fa/setup').flush({
      code: 0,
      message: 'ok',
      data: { secret: 'S', qr_url: 'otpauth://totp/x' },
    });
    probe().code.set('123456');
    probe().cancelSetup();
    expect(probe().setup()).toBeNull();
    expect(probe().code()).toBe('');
    http.expectNone('/api/v1/user/2fa/enable');
  });
});
