/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { use } from '../core/i18n/i18n';
import { Users } from './users';

/**
 * 平台用户注销的钉子。上一批的教训是「护栏只看函数名不看实参」——所以这里钉的是
 * 具体端点串、HTTP 方法、以及「成功以回读为准」的两个方向，而不只是「调了 destroy」。
 * 后端拒绝（余额非零）时服务端原因必须原样透出，也一并钉住。
 */
describe('Users 平台用户注销', () => {
  let http: HttpTestingController;
  let page: Users;
  let confirmSpy: ReturnType<typeof vi.spyOn>;

  // destroy 是 protected，且类只从模板/测试调用 —— 测试侧按鸭子类型取用
  const call = (row: Record<string, unknown>): Promise<void> =>
    (page as unknown as { destroy(r: Record<string, unknown>): Promise<void> }).destroy(row);
  const errorText = (): string => (page as unknown as { error(): string }).error();

  beforeEach(() => {
    // 界面文案是词条（「仍在列表中」那类断言断的是中文译文）⇒ 语言必须显式定
    use('zh');
    TestBed.configureTestingModule({ providers: [provideHttpClient(), provideHttpClientTesting()] });
    http = TestBed.inject(HttpTestingController);
    // inject(Api) 写在字段初始化器里 ⇒ 必须在注入上下文里构造
    page = TestBed.runInInjectionContext(() => new Users());
    confirmSpy = vi.spyOn(globalThis, 'confirm').mockReturnValue(true);
  });

  afterEach(() => {
    confirmSpy.mockRestore();
    try {
      http.verify();
    } finally {
      // 必须显式复位：否则用例失败留下的未决请求会污染下一个用例的 configureTestingModule
      TestBed.resetTestingModule();
    }
  });

  /** 让被测代码跑过 await 的微任务，好让它发出的下一个请求进到 HttpTestingController */
  const tick = (): Promise<void> => new Promise((resolve) => setTimeout(resolve, 0));

  const row = { id: 'HASHID1', username: 'alice' };

  it('打 DELETE /admin/v1/platform/user/{hashid}，回读后该用户不在列表里即算成功', async () => {
    const done = call(row);

    const del = http.expectOne((r) => r.method === 'DELETE');
    // 端点串要逐字对：打错到 /admin/v1/user/* 就是删管理员（AdminUser），实参也得是 hashid
    expect(del.request.url).toBe('/admin/v1/platform/user/HASHID1');
    del.flush({ code: 0, message: '注销成功', data: { count: 1, already_deleted: false } });

    await tick();
    const list = http.expectOne((r) => r.method === 'GET');
    expect(list.request.url.startsWith('/admin/v1/platform/user/list')).toBe(true);
    list.flush({ code: 0, message: 'ok', data: { list: [], total: 0 } });

    await done;
    expect(errorText()).toBe('');
  });

  it('回读发现该用户仍在列表里就报错，不静默当成功', async () => {
    const done = call(row);

    http.expectOne((r) => r.method === 'DELETE').flush({ code: 0, message: '注销成功', data: { count: 1 } });

    await tick();
    http.expectOne((r) => r.method === 'GET').flush({ code: 0, message: 'ok', data: { list: [row], total: 1 } });

    await done;
    expect(errorText()).toContain('仍在列表中');
  });

  it('后端拒绝（余额非零）时把服务端原因原样显示，不吞成「操作失败」', async () => {
    const done = call(row);

    http.expectOne((r) => r.method === 'DELETE').flush(
      { code: 422, message: '该用户仍有余额或冻结金额，请先结清后再注销' },
      { status: 422, statusText: 'Unprocessable Entity' },
    );

    await done;
    expect(errorText()).toContain('仍有余额或冻结金额');
    // 被拒绝就不该有回读
    await tick();
    http.expectNone((r) => r.method === 'GET');
  });

  it('用户取消确认时不发任何请求', async () => {
    confirmSpy.mockReturnValue(false);
    await call(row);
    await tick();
    http.expectNone(() => true);
  });
});

/**
 * 抽屉里的动作报错必须**看得见**。
 *
 * 审计真机读数：驳回失败时错误元素 `rect [254,169,1172,84]`、`position:static; z-index:auto`，
 * `document.elementFromPoint(错误中心)` 命中的是 `DIV.backdrop`（抽屉遮罩，`position:fixed`、
 * z-index 50、铺满 1440×900）—— 运营点完「驳回」屏幕上什么都没有，只剩遮罩下被压暗的一行红字，
 * 于是重复点；而驳回是不可逆的（后端 CAS）。判据照抄审计那条：错误元素本身要能接住 pointer，
 * 也就是必须在抽屉 DOM 里，而不是被遮罩盖住的页面级 `.state.error`。
 */
describe('Users 动作报错就地显示', () => {
  let http: HttpTestingController;

  const tick = (): Promise<void> => new Promise((resolve) => setTimeout(resolve, 0));
  const ID_ROW: Record<string, unknown> = {
    id: 'IDHASH1',
    user: { id: 'UHASH1', username: 'bob' },
  };

  beforeEach(() => {
    use('zh');
    TestBed.configureTestingModule({ providers: [provideHttpClient(), provideHttpClientTesting()] });
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => {
    try {
      http.verify();
    } finally {
      TestBed.resetTestingModule();
    }
  });

  it('实名审核失败：错误渲染在抽屉内部（改前灌的是页面级 .state.error，在 backdrop 底下）', async () => {
    const f: ComponentFixture<Users> = TestBed.createComponent(Users);
    f.detectChanges(); // ngOnInit → 首屏列表 GET
    http
      .expectOne((r) => r.method === 'GET')
      .flush({ code: 0, message: 'ok', data: { list: [], total: 0 } });
    await tick();
    f.detectChanges();

    const u = f.componentInstance as unknown as {
      pick(k: string): void;
      open(r: Record<string, unknown>): Promise<void>;
      review(r: Record<string, unknown>, a: 'approve' | 'reject'): Promise<void>;
    };
    // 切到实名审核（另一个 ID 空间），拿一行开抽屉
    u.pick('identity');
    const list = http.expectOne((r) => r.method === 'GET');
    expect(list.request.url.startsWith('/admin/v1/identity/list')).toBe(true);
    list.flush({ code: 0, message: 'ok', data: { list: [ID_ROW], total: 1 } });
    await tick();
    f.detectChanges();

    await u.open(ID_ROW); // identity 标签页不拉详情（端点与 ID 空间必须成对）
    f.detectChanges();
    expect(f.nativeElement.querySelector('.drawer')).toBeTruthy();

    const done = u.review(ID_ROW, 'approve');
    http
      .expectOne((r) => r.method === 'PUT')
      .flush(
        { code: 422, message: '该实名记录已被处理，请刷新', data: null },
        { status: 422, statusText: 'Unprocessable Entity' },
      );
    await done;
    f.detectChanges();

    const alert = f.nativeElement.querySelector('.drawer .alert');
    expect(alert?.textContent).toContain('已被处理');
    // 页面级那处**不能**同时挂着：它才是被 backdrop 盖住、看不见的那一份
    expect(f.nativeElement.querySelector('.state.error')).toBeNull();
  });
});

/**
 * ② 实名列表的 `user` 列：行里 `user` 是**嵌套对象**（IdentityController::list 的
 * `$data['user'] = ['id','username']`），而 `dash()` 对对象一律渲染成 `{…}` ——
 * 运营在列表上看到的那一列等于什么都没说。参照树 react 的口径（modules.ts:502 identityLabel）
 * 是把对象里的 username 取出来用，这里同理：摊平成用户名字符串。
 */
describe('Users 实名列表的对象标识', () => {
  let http: HttpTestingController;

  const tick = (): Promise<void> => new Promise((resolve) => setTimeout(resolve, 0));
  const ID_ROW: Record<string, unknown> = {
    id: 'IDHASH1',
    real_name: '张三',
    id_type: 'id_card',
    status: 'pending',
    user: { id: 'UHASH1', username: 'bob' },
  };

  beforeEach(() => {
    use('zh');
    TestBed.configureTestingModule({ providers: [provideHttpClient(), provideHttpClientTesting()] });
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => {
    try {
      http.verify();
    } finally {
      TestBed.resetTestingModule();
    }
  });

  it('user 列渲染出用户名（改前渲染的是嵌套对象 ⇒ `{…}`）', async () => {
    const f: ComponentFixture<Users> = TestBed.createComponent(Users);
    f.detectChanges();
    http
      .expectOne((r) => r.method === 'GET')
      .flush({ code: 0, message: 'ok', data: { list: [], total: 0 } });
    await tick();
    f.detectChanges();

    (f.componentInstance as unknown as { pick(k: string): void }).pick('identity');
    const list = http.expectOne((r) => r.method === 'GET');
    expect(list.request.url.startsWith('/admin/v1/identity/list')).toBe(true);
    list.flush({ code: 0, message: 'ok', data: { list: [ID_ROW], total: 1 } });
    await tick();
    f.detectChanges();

    const el = f.nativeElement as HTMLElement;
    // 按**表头**定位那一列（`user` 没有 col.* 词条 ⇒ 表头就是字段名本身），
    // 不按下标写死：列的增删改序不该让这条断言跟着失效
    const heads = [...el.querySelectorAll('thead th')].map((n) => n.textContent!.trim());
    const at = heads.indexOf('user');
    expect(at).toBeGreaterThanOrEqual(0);
    const tds = [...el.querySelectorAll('tbody tr:first-child td')].map((n) => n.textContent!.trim());
    expect(tds[at]).toBe('bob');
    // 整行都不许再出现对象占位符（那正是缺陷的样子）
    expect(el.querySelector('tbody tr')!.textContent).not.toContain('{');
  });
});
