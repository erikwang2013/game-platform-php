/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
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
