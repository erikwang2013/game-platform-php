/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { ActivatedRoute, convertToParamMap, provideRouter } from '@angular/router';
import { tokens } from '../core/api.service';
import { LoginPage } from './login';

type Sig<T> = { (): T; set(v: T): void };
type RegForm = {
  setValue(v: { username: string; email: string; password: string; nickname: string; invite: string }): void;
  controls: { invite: { value: string } };
};
type Probe = {
  login(): void;
  register(): void;
  onProof(p: { captcha_key: string; clicks: { x: number; y: number }[] }): void;
  verify2fa(): void;
  cancel2fa(): void;
  loginForm: { setValue(v: { username: string; password: string }): void };
  regForm: RegForm;
  tab: Sig<'in' | 'up'>;
  tfa: Sig<string>;
  code2fa: Sig<string>;
  error: Sig<string>;
  busy: Sig<boolean>;
};

/**
 * 二次验证（2FA）登录第二步的钉子。
 *
 * 以前这里是**死路**：done() 见到 require_2fa 就写一条「本客户端暂不支持」的错误，
 * 于是任何开了 2FA 的账号在这个客户端**完全登不进来**。服务端其实一直是两步流程
 * （AuthController 下发 pending_2fa_token，TwoFactorController::verify 用票据换正式令牌）。
 *
 * 钉三件：① 见到 require_2fa 要进第二步、且**不**落令牌；② 第二步的请求体形状
 * （pending_2fa_token + code）；③ 第二步成功后才落令牌，码错留在原地可重试。
 */
describe('LoginPage 2FA 第二步', () => {
  let http: HttpTestingController;
  let page: LoginPage;

  const probe = (): Probe => page as unknown as Probe;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        // 空路由表会让成功后的 navigateByUrl('/') 以 NG04002 被拒（未处理拒绝 ⇒ 套件判红）
        provideRouter([
          { path: 'login', children: [] },
          { path: '', children: [] },
        ]),
      ],
    });
    http = TestBed.inject(HttpTestingController);
    // inject(Api)/inject(Router) 写在字段初始化器里 ⇒ 必须在注入上下文里构造
    page = TestBed.runInInjectionContext(() => new LoginPage());
  });

  afterEach(() => {
    tokens.clear();
    try {
      http.verify();
    } finally {
      // 必须显式复位：用例失败留下的未决请求会污染下一个用例的 configureTestingModule
      TestBed.resetTestingModule();
    }
  });

  /** 走到「服务端回了 require_2fa」这一步：填表 → 过验证码弹框 → 登录响应 */
  function reach2fa(pendingToken: string | undefined): void {
    const p = probe();
    p.loginForm.setValue({ username: 'alice', password: 'secret123' });
    p.login();
    p.onProof({ captcha_key: 'k1', clicks: [{ x: 1, y: 2 }, { x: 3, y: 4 }] });
    http.expectOne('/api/v1/auth/login').flush({
      code: 0,
      message: 'ok',
      data: {
        access_token: '',
        refresh_token: '',
        require_2fa: true,
        ...(pendingToken === undefined ? {} : { pending_2fa_token: pendingToken }),
      },
    });
  }

  it('见到 require_2fa 进入第二步，且此时不落任何令牌', () => {
    reach2fa('tk-1');
    expect(probe().tfa()).toBe('tk-1');
    // 关键：没拿到正式令牌前不许写 localStorage，否则会被当成已登录
    expect(tokens.access()).toBe('');
    expect(tokens.refresh()).toBe('');
    expect(probe().error()).toBe('');
  });

  it('服务端要求 2FA 却没发票据 ⇒ 报错而不是进第二步', () => {
    reach2fa(undefined);
    expect(probe().tfa()).toBe('');
    expect(probe().error()).toContain('票据');
  });

  it('第二步请求体恰为 pending_2fa_token + code，成功后才落令牌', () => {
    reach2fa('tk-2');
    probe().code2fa.set('123456');
    probe().verify2fa();

    const req = http.expectOne('/api/v1/2fa/verify');
    expect(req.request.method).toBe('POST');
    expect(req.request.body).toEqual({ pending_2fa_token: 'tk-2', code: '123456' });

    req.flush({
      code: 0,
      message: 'ok',
      data: { access_token: 'at-new', refresh_token: 'rt-new' },
    });
    expect(tokens.access()).toBe('at-new');
    expect(tokens.refresh()).toBe('rt-new');
    expect(probe().tfa()).toBe('');
  });

  it('码错（422）留在第二步可重试，票据不丢、令牌不落', () => {
    reach2fa('tk-3');
    probe().code2fa.set('000000');
    probe().verify2fa();
    http
      .expectOne('/api/v1/2fa/verify')
      .flush({ code: 422, message: 'Invalid TOTP code', data: [] });

    expect(probe().tfa()).toBe('tk-3');
    expect(probe().busy()).toBe(false);
    expect(probe().error()).toBe('Invalid TOTP code');
    expect(tokens.access()).toBe('');
  });

  it('票据失效（401）退回登录表单，不把用户困在第二步', () => {
    reach2fa('tk-4');
    probe().code2fa.set('123456');
    probe().verify2fa();
    http
      .expectOne('/api/v1/2fa/verify')
      .flush({ code: 401, message: 'Invalid or expired verification session', data: [] });

    expect(probe().tfa()).toBe('');
    expect(probe().error()).toContain('请重新登录');
    expect(tokens.access()).toBe('');
  });

  it('空码不发请求', () => {
    reach2fa('tk-5');
    probe().code2fa.set('   ');
    probe().verify2fa();
    http.expectNone('/api/v1/2fa/verify');
    expect(probe().tfa()).toBe('tk-5');
  });

  /**
   * 长度闸：只放行 6 位（TOTP）与 10 位（备份码）。
   *
   * 服务端是 `between:6,10`，**7~9 位两种都不是** ⇒ 必然 422。本地先拦掉这次注定失败的往返。
   * 10 位那条尤其要钉：备份码是大小写字母+数字（generateBackupCode()），
   * 长度闸**不能顺手加数字校验**，否则 10 位备份码在本地就被拒了 —— 服务端修好的分支又白修。
   */
  it('长度闸：6 位与 10 位放行，7~9 位本地拦下且不发请求', () => {
    reach2fa('tk-len');
    for (const bad of ['1234567', '12345678', '123456789']) {
      probe().code2fa.set(bad);
      probe().verify2fa();
      expect(probe().error(), `${bad} 应该被本地拦下`).toContain('10 位备份码');
      http.expectNone('/api/v1/2fa/verify');
      expect(probe().busy()).toBe(false);
    }
    // 10 位字母数字备份码必须放行（一路发到服务端）
    probe().code2fa.set('aB3xY9pQz1');
    probe().verify2fa();
    expect(http.expectOne('/api/v1/2fa/verify').request.body).toEqual({
      pending_2fa_token: 'tk-len',
      code: 'aB3xY9pQz1',
    });
  });

  it('返回按钮清掉票据与码，且不发请求', () => {
    reach2fa('tk-6');
    probe().code2fa.set('123456');
    probe().cancel2fa();
    expect(probe().tfa()).toBe('');
    expect(probe().code2fa()).toBe('');
    expect(probe().busy()).toBe(false);
    http.expectNone('/api/v1/2fa/verify');
  });
});

/**
 * DOM 级回归：**点按钮**必须真的发请求。
 *
 * 上面那组用例全是直接调 verify2fa()，所以它们在下面这个真缺陷面前**全绿**：
 * 第二步的表单没有 [formGroup]，`(ngSubmit)` 就是死绑定（NgForm 不在 ReactiveFormsModule
 * 的导出里），浏览器于是走原生提交 —— 真机实测点下去 location.href 变成
 * `/login?code2fa=123456`、整页刷新、一个请求都不发。只有「渲染 + 点按钮」才看得见。
 */
describe('LoginPage 2FA 提交按钮（DOM 级）', () => {
  let http: HttpTestingController;
  let fixture: ComponentFixture<LoginPage>;

  beforeEach(() => {
    TestBed.configureTestingModule({
      imports: [LoginPage],
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        provideRouter([
          { path: 'login', children: [] },
          { path: '', children: [] },
        ]),
      ],
    });
    http = TestBed.inject(HttpTestingController);
    fixture = TestBed.createComponent(LoginPage);
    // 直接置成「已过密码校验、等第二步码」的状态，绕开验证码弹框
    (fixture.componentInstance as unknown as Probe).tfa.set('TK-9');
    fixture.detectChanges();
  });

  afterEach(() => {
    tokens.clear();
    try {
      http.verify();
    } finally {
      TestBed.resetTestingModule();
    }
  });

  it('填码后点「验证并登录」发出 POST /api/v1/2fa/verify（不是原生提交刷页）', () => {
    const el: HTMLElement = fixture.nativeElement;
    const input = el.querySelector<HTMLInputElement>('input[name=code2fa]');
    expect(input, '第二步的验证码输入框').toBeTruthy();
    input!.value = '123456';
    input!.dispatchEvent(new Event('input', { bubbles: true }));
    fixture.detectChanges();

    const btn = el.querySelector<HTMLButtonElement>('.auth-card form button[type=submit]');
    expect(btn, '提交按钮').toBeTruthy();
    expect(btn!.disabled, '码填了按钮就该可用').toBe(false);
    btn!.click();
    fixture.detectChanges();

    const req = http.expectOne('/api/v1/2fa/verify');
    expect(req.request.body).toEqual({ pending_2fa_token: 'TK-9', code: '123456' });
    req.flush({ code: 0, message: 'ok', data: { access_token: 'at', refresh_token: 'rt' } });
    expect(tokens.access()).toBe('at');
  });

  /**
   * 服务端 `enable()` 一次发 8 个**10 位**备份码（大小写字母+数字），`verify()` 的 validator 是
   * `between:6,10`（6=TOTP、10=备份码）。客户端这边曾写死 `maxlength=6` ⇒ 备份码被**输入框自己
   * 截成 6 位**再送去校验，必然 422 —— 这是服务端那个 `size:6` 缺陷在客户端的镜像，服务端放宽了
   * 也救不回来。
   *
   * 这条只能钉属性，钉不了行为：**jsdom 不执行 maxlength**。实测（探针已删）给 `maxlength=6`
   * 的框直接赋 10 字符 value，读回是完整 10 位且 `validity.tooLong === false` ⇒ 在 jsdom 里写
   * 「10 位原样送出」是**恒真断言**，`maxlength=6` 也照样绿。真正的截断由真浏览器的真实键入证明
   * （/tmp/angular_cend_2fa_e2e.mjs 里用 CDP Input.insertText 走真实编辑管线）。
   */
  it('长度上限为 10 且提示提到备份码（写死 6 会截断 10 位备份码）', () => {
    const el: HTMLElement = fixture.nativeElement;
    const input = el.querySelector<HTMLInputElement>('input[name=code2fa]');
    expect(input, '第二步的验证码输入框').toBeTruthy();
    expect(input!.getAttribute('maxlength')).toBe('10');
    // 备份码含大小写字母，inputmode=numeric 会引导出纯数字键盘，手机上根本打不出来
    expect(input!.getAttribute('inputmode'), '备份码含字母，不能引导数字键盘').toBeNull();
    expect(el.querySelector('.hint2')?.textContent ?? '').toContain('备份码');
    expect(input!.getAttribute('placeholder') ?? '').toContain('备份码');
  });
});

/**
 * 邀请链接落地页（`/login?code=xxx`）。
 *
 * 这一块是分享短码的**另一半**：`POST /shares` 生成的码，如果没人上报点击，
 * `clicks` 恒 0 —— 邀请链接就只剩一个假仪式。落地页做两件事：
 *  ① 预填邀请码并把页签落在**注册**（码只在注册时有意义）；
 *  ② 发**一次** `POST /shares/visit`（公开匿名路由）上报点击，失败静默不挡注册。
 * 注册时再把码透传成 `share_code`（服务端 `ShareLink::bindConversion` 落转化）。
 *
 * ⚠ 空码**不能进请求体**：`share_code: ''` 会被服务端 nullable 放行，
 * 于是「这次注册带了邀请」这件事在服务端看不出来，而客户端以为带了。
 */
describe('LoginPage 邀请链接落地页', () => {
  let http: HttpTestingController;

  const boot = (query: string): LoginPage => {
    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        provideRouter([
          { path: 'login', children: [] },
          { path: '', children: [] },
        ]),
        // 直接钉快照，不去跑真路由：本用例只关心「读到的码」和「据此发出的请求」
        { provide: ActivatedRoute, useValue: { snapshot: { queryParamMap: convertToParamMap({ code: query }) } } },
      ],
    });
    http = TestBed.inject(HttpTestingController);
    return TestBed.runInInjectionContext(() => new LoginPage());
  };

  afterEach(() => {
    tokens.clear();
    try {
      http.verify();
    } finally {
      TestBed.resetTestingModule();
    }
  });

  it('带 ?code= 进来：落在注册页签、码预填、并**上报一次**点击', () => {
    const page = boot('AbC12345');
    const p = page as unknown as Probe;

    expect(p.tab()).toBe('up');
    expect(p.regForm.controls.invite.value).toBe('AbC12345');

    const req = http.expectOne('/api/v1/shares/visit');
    expect(req.request.method).toBe('POST');
    expect(req.request.body).toEqual({ short_code: 'AbC12345' });
    req.flush({ code: 0, message: 'ok', data: [] });
  });

  it('上报失败（码已失效 404）静默：不写错误、不挡住注册', () => {
    const p = boot('AbC12345') as unknown as Probe;
    http
      .expectOne('/api/v1/shares/visit')
      .flush({ code: 404, message: 'Invalid short code', data: [] });

    expect(p.error()).toBe('');
    expect(p.tab()).toBe('up');
  });

  it('没有 ?code=：落在登录页签，且**一个请求都不发**', () => {
    const p = boot('') as unknown as Probe;
    expect(p.tab()).toBe('in');
    http.expectNone(() => true);
  });

  it('码超过服务端上限（max:12）就当没有：不预填、不上报', () => {
    const p = boot('AbC123456789012') as unknown as Probe;
    expect(p.tab()).toBe('in');
    expect(p.regForm.controls.invite.value).toBe('');
    http.expectNone(() => true);
  });

  it('注册时把码透传成 share_code；**空码不进请求体**（多余空串会造假象）', () => {
    const p = boot('AbC12345') as unknown as Probe;
    http.expectOne('/api/v1/shares/visit').flush({ code: 0, message: 'ok', data: [] });

    const body = (invite: string): Record<string, unknown> => {
      p.regForm.setValue({
        username: 'alice',
        email: 'a@b.co',
        password: 'Secret123',
        nickname: '',
        invite,
      });
      p.register();
      p.onProof({ captcha_key: 'k1', clicks: [{ x: 1, y: 2 }, { x: 3, y: 4 }] });
      const req = http.expectOne('/api/v1/auth/register');
      const sent = req.request.body as Record<string, unknown>;
      req.flush({ code: 0, message: 'ok', data: { access_token: 'at', refresh_token: 'rt' } });
      return sent;
    };

    const withCode = body('Zz9');
    expect(withCode['share_code']).toBe('Zz9');
    expect(withCode['username']).toBe('alice');
    tokens.clear();

    const without = body('');
    expect('share_code' in without, '空邀请码时请求体里不该有 share_code 这个键').toBe(false);
  });
});
