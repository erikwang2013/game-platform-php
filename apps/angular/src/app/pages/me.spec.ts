/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { tokens } from '../core/api.service';
import { Msg, t } from '../core/i18n/i18n';
import { MePage } from './me';

/**
 * C 端注销账号的钉子。钉三件容易退化的东西：
 * ① 端点与请求体形状（password + confirm 恰为 'yes'，服务端契约见 UserController::deleteAccount）；
 * ② 「成功以回读为准」——回读仍读得到资料就不算注销成功；
 * ③ 服务端拒绝原因原样透出（「请先提现所有余额后再注销账号」不许被吞成「操作失败」）。
 */
describe('MePage 注销账号', () => {
  let http: HttpTestingController;
  let page: MePage;

  type Probe = {
    submitDel(): void;
    delOpen(): boolean;
    delMsg(): Msg;
    delBusy(): boolean;
    delPw: { set(v: string): void };
    delYes: { set(v: string): void };
  };
  /**
   * `Msg` 是**两态**（词条键 / 服务端原文）：只有键那一态的读点需要解析，
   * 原文那一态原样透出。**故意不统一**：直接把原文那两处也裹进来，就再也证明不了
   * 「服务端 message 被原样透出」这一支（裹了以后键和原文的差别被抹平）。
   */
  const txt = (v: Msg): string => (typeof v === 'string' ? v : t(v.key, v.params));
  const probe = (): Probe => page as unknown as Probe;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        // 空路由表会让注销后的 navigate(['/login']) 以 NG04002 被拒（未处理拒绝 ⇒ 套件判红）
        provideRouter([{ path: 'login', children: [] }]),
      ],
    });
    http = TestBed.inject(HttpTestingController);
    // inject(Api)/inject(Router) 写在字段初始化器里 ⇒ 必须在注入上下文里构造
    page = TestBed.runInInjectionContext(() => new MePage());
    // 构造即发起三个首屏请求
    http.expectOne((r) => r.url === '/api/v1/user/profile').flush({ code: 0, message: 'ok', data: {} });
    http
      .expectOne((r) => r.url.endsWith('/notification/unread-count'))
      .flush({ code: 0, message: 'ok', data: { count: 0 } });
    http
      .expectOne((r) => r.url.endsWith('/notification/list'))
      .flush({ code: 0, message: 'ok', data: { items: [], page: 1, last_page: 1, total: 0 } });
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

  /** 让被测代码走完同步的 subscribe 链 */
  const submit = (pw = 'secret', yes = 'yes'): void => {
    probe().delPw.set(pw);
    probe().delYes.set(yes);
    probe().submitDel();
  };

  it('按要求发 POST /api/v1/user/delete-account，回读确认账号取不到才算成功', () => {
    tokens.save('acc', 'ref');
    submit();

    const req = http.expectOne((r) => r.url === '/api/v1/user/delete-account');
    expect(req.request.method).toBe('POST');
    // 请求体逐字对：字段名与 confirm 的字面量 literal 都是服务端契约的一部分
    expect(req.request.body).toEqual({ password: 'secret', confirm: 'yes' });
    req.flush({ code: 0, message: '账号已注销。感谢您的使用。', data: [] });

    // 回读：注销后资料接口必须已取不到
    http
      .expectOne((r) => r.url === '/api/v1/user/profile')
      .flush({ code: 401, message: '未登录或登录已过期', data: null });

    expect(probe().delMsg()).toBe('');
    expect(tokens.access()).toBe('');
    expect(tokens.refresh()).toBe('');
  });

  it('回读仍能读到资料就不算注销成功，如实报告而不是宣布成功', () => {
    tokens.save('acc', 'ref');
    submit();
    http.expectOne((r) => r.url === '/api/v1/user/delete-account').flush({ code: 0, message: 'ok', data: [] });
    http.expectOne((r) => r.url === '/api/v1/user/profile').flush({ code: 0, message: 'ok', data: { id: 'U1' } });

    expect(txt(probe().delMsg())).toContain('仍可读取');
    expect(tokens.access()).toBe('acc');
    expect(probe().delBusy()).toBe(false);
  });

  it('服务端拒绝（有余额）时把原因原样显示，不吞成「操作失败」，也不发回读', () => {
    submit();
    http
      .expectOne((r) => r.url === '/api/v1/user/delete-account')
      .flush(
        { code: 422, message: '请先提现所有余额后再注销账号' },
        { status: 422, statusText: 'Unprocessable Entity' },
      );

    expect(probe().delMsg()).toBe('请先提现所有余额后再注销账号');
    http.expectNone((r) => r.url === '/api/v1/user/profile');
  });

  it('confirm 不是 yes 时也把服务端的原文透出', () => {
    submit('secret', 'no');
    http
      .expectOne((r) => r.url === '/api/v1/user/delete-account')
      .flush({ code: 422, message: '请输入 yes 确认注销' }, { status: 422, statusText: 'Unprocessable Entity' });

    expect(probe().delMsg()).toBe('请输入 yes 确认注销');
  });

  it('回读遇到网络故障时报「无法确认」，不把不确定当成功', () => {
    tokens.save('acc', 'ref');
    submit();
    http.expectOne((r) => r.url === '/api/v1/user/delete-account').flush({ code: 0, message: 'ok', data: [] });
    http
      .expectOne((r) => r.url === '/api/v1/user/profile')
      .error(new ProgressEvent('error'), { status: 0, statusText: 'Unknown Error' });

    expect(txt(probe().delMsg())).toContain('无法确认');
    expect(tokens.access()).toBe('acc');
  });
});

/**
 * 改昵称与父组件的**接线**（子组件自己的行为在 me-nick.spec.ts）。
 *
 * 这条必须整页渲染才有意义：单测子组件只能证明「它发出了 saved」，
 * 证明不了父组件真的据此回读了资料 —— 而「以服务端落库值为准」正是这一条的全部价值。
 */
describe('MePage 改昵称接线', () => {
  let http: HttpTestingController;
  let fixture: ComponentFixture<MePage>;

  const el = (): HTMLElement => fixture.nativeElement as HTMLElement;
  const btn = (text: string): HTMLButtonElement => {
    const hit = [...el().querySelectorAll('button')].find((b) => b.textContent?.trim() === text);
    if (!hit) throw new Error(`没有文案为「${text}」的按钮`);
    return hit as HTMLButtonElement;
  };

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        provideRouter([{ path: 'login', children: [] }]),
      ],
    });
    http = TestBed.inject(HttpTestingController);
    fixture = TestBed.createComponent(MePage);
    fixture.detectChanges();
    // MeTiles / MeExport 首屏都不抓数据，渲染整页也只有这三个请求
    http
      .expectOne((r) => r.url === '/api/v1/user/profile')
      .flush({ code: 0, message: 'ok', data: { id: 'U1', username: 'bob', nickname: '旧昵称', avatar: '' } });
    http
      .expectOne((r) => r.url.endsWith('/notification/unread-count'))
      .flush({ code: 0, message: 'ok', data: { count: 0 } });
    http
      .expectOne((r) => r.url.endsWith('/notification/list'))
      .flush({ code: 0, message: 'ok', data: { items: [], page: 1, last_page: 1, total: 0 } });
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

  it('保存成功后父组件回读资料，页头的昵称换成服务端落库值', () => {
    expect(el().querySelector('.who')?.textContent?.trim()).toBe('旧昵称');

    btn('改昵称').click();
    fixture.detectChanges();
    const input = el().querySelector('input') as HTMLInputElement;
    input.value = '新昵称';
    input.dispatchEvent(new Event('input'));
    btn('保存').click();
    fixture.detectChanges();

    http
      .expectOne((r) => r.url === '/api/v1/user/profile' && r.method === 'PUT')
      .flush({ code: 0, message: 'ok', data: { id: 'U1', username: 'bob', nickname: '新昵称', avatar: '' } });

    // 回读：不是拿本地输入当第二真值源
    http
      .expectOne((r) => r.url === '/api/v1/user/profile' && r.method === 'GET')
      .flush({ code: 0, message: 'ok', data: { id: 'U1', username: 'bob', nickname: '新昵称', avatar: '' } });
    fixture.detectChanges();

    expect(el().querySelector('.who')?.textContent?.trim()).toBe('新昵称');
    expect(el().querySelector('.modal')).toBeNull();
  });
});
