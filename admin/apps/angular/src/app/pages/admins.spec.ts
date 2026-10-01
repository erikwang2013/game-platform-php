/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { Row } from '../core/api.service';
import { Field } from '../core/crud';
import { use } from '../core/i18n/i18n';
import { Admins } from './admins';

/**
 * 「管理员」（管理端后台账号，/admin/v1/user）的接线钉子。
 *
 * 三条是这个模块最容易踩空、且踩了不报错的地方：
 *  1. **端点串**：打错成 /admin/v1/platform/user/* 就是去操作 C 端平台用户（另一个 ID 空间）；
 *     打错成 /user/list（resource 没有这个路径）列表恒空，界面只显示「暂无数据」。
 *  2. **role_ids 必须整个键进 JSON 体**：空数组的语义是「收回全部角色」，而后端判
 *     `$request->has('role_ids')` —— urlencoded 表达不了空数组（键会消失），清空角色静默失效。
 *  3. **重置密码的两个字段名**：password 是新密码、admin_password 是**操作者自己的**密码
 *     （走 confirmPassword）。两个混了/漏了，要么改不了密码，要么「改别人密码」不需要任何确认。
 * 外加自我保护：停用/删除当前登录的自己要在**发请求之前**挡下。
 */
describe('Admins 管理端账号', () => {
  let http: HttpTestingController;
  let confirmSpy: ReturnType<typeof vi.spyOn>;
  let promptSpy: ReturnType<typeof vi.spyOn>;

  /** 当前登录者（Auth 从 localStorage 的 ga_user 读；读的是 hashid，与列表行的 id 同一空间） */
  const ME = { id: 'MEHASH', username: 'root', real_name: '超级管理员' };
  const OTHER = {
    id: 'OTHERHASH',
    username: 'ops',
    real_name: '运营甲',
    phone: '138****8888',
    email: 'o***@x.com',
    status: 1,
    role_ids: ['ROLE1'],
  };
  const ROLES = [{ id: 'ROLE1', name: '运营', slug: 'ops' }];

  /** 页面方法多是 protected，测试侧按鸭子类型取用 */
  type A = {
    load(): Promise<void>;
    rows(): Row[];
    error(): string;
    run(row: Row, key: string): Promise<void>;
    submit(values: Row): Promise<void>;
    submitAct(values: Row): Promise<void>;
    formFields(): Field[];
    actFields(): Field[];
  };

  beforeEach(() => {
    // 语言钉在 en：本文件断的是**英文成品**（界面文案已抽成词条），
    // 不钉住的话「上一条用例留下的语言」会让断言随执行顺序变红
    use('en');
    // 必须在 new Admins() 之前落盘：Auth 是 providedIn:'root'，构造时就读 localStorage
    localStorage.setItem('ga_user', JSON.stringify(ME));
    TestBed.configureTestingModule({ providers: [provideHttpClient(), provideHttpClientTesting()] });
    http = TestBed.inject(HttpTestingController);
    confirmSpy = vi.spyOn(globalThis, 'confirm').mockReturnValue(true);
    promptSpy = vi.spyOn(globalThis, 'prompt').mockReturnValue('pwd');
  });

  afterEach(() => {
    confirmSpy.mockRestore();
    promptSpy.mockRestore();
    localStorage.clear();
    try {
      http.verify();
    } finally {
      // 必须显式复位：否则用例失败留下的未决请求会污染下一个用例的 configureTestingModule
      TestBed.resetTestingModule();
    }
  });

  /** 让被测代码跑过 await 的微任务，好让它发出的下一个请求进到 HttpTestingController */
  const tick = (): Promise<void> => new Promise((resolve) => setTimeout(resolve, 0));
  /** TestRequest 上挂着 .request，而 expectOne 回调拿到的是 HttpRequest 本身 —— 两种都收 */
  const url = (x: { request: { url: string } } | { url: string }): string =>
    ('request' in x ? x.request.url : x.url).split('?')[0]!;
  /** inject(Api) 在字段初始化器里 ⇒ 必须有注入上下文 */
  const build = (): A => TestBed.runInInjectionContext(() => new Admins()) as unknown as A;

  /** 首屏取数：fetch() 里 Promise.all 同批发出列表与角色候选，两个 GET 都要应答 */
  const loadList = async (p: A, rows: Row[]): Promise<void> => {
    const done = p.load();
    http
      .expectOne((r) => r.method === 'GET' && url(r) === '/admin/v1/user')
      .flush({ code: 0, message: 'ok', data: { list: rows, total: rows.length } });
    http
      .expectOne((r) => r.method === 'GET' && url(r) === '/admin/v1/role')
      .flush({ code: 0, message: 'ok', data: { list: ROLES, total: ROLES.length } });
    await done;
  };

  /** 写操作成功之后基类会回读列表（外加角色候选）—— 一次全部应答掉 */
  const flushReload = async (): Promise<void> => {
    await tick();
    http
      .expectOne((r) => r.method === 'GET' && url(r) === '/admin/v1/user')
      .flush({ code: 0, message: 'ok', data: { list: [], total: 0 } });
    http
      .expectOne((r) => r.method === 'GET' && url(r) === '/admin/v1/role')
      .flush({ code: 0, message: 'ok', data: { list: ROLES, total: ROLES.length } });
  };

  it('列表打 /admin/v1/user（resource 没有 /list；更不是 C 端的 platform/user），且带上后端认的 limit', async () => {
    const p = build();
    const done = p.load();
    const req = http.expectOne((r) => r.method === 'GET' && url(r) === '/admin/v1/user');
    // page_size 扇出的三个别名里，UserController::index 读的是 limit（缺省 15）——
    // 一个都不发的话服务端退回 15，而本页 pageSize=20 ⇒ 尾页永远取不到
    expect(req.request.urlWithParams).toContain('limit=20');
    req.flush({ code: 0, message: 'ok', data: { list: [OTHER], total: 1 } });
    http
      .expectOne((r) => r.method === 'GET' && url(r) === '/admin/v1/role')
      .flush({ code: 0, message: 'ok', data: { list: ROLES, total: ROLES.length } });
    await done;

    // 端点串逐字对（上面的 matcher）：写操作落在 /admin/v1/user/* 上才是管理员 ——
    // 打到 /admin/v1/platform/user/* 就是去动 C 端平台用户，那是另一个 ID 空间
    expect(p.rows().length).toBe(1);
  });

  it('新建：POST /admin/v1/user，role_ids 以数组进 body；留空的 phone/email 不发', async () => {
    const p = build();
    const done = p.submit({
      username: 'ops2',
      password: 'Abcd1234',
      real_name: '运营乙',
      phone: '',
      email: '',
      role_ids: ['ROLE1'],
    });
    const post = http.expectOne((r) => r.method === 'POST');
    expect(url(post)).toBe('/admin/v1/user');
    // role_ids 必须是数组本身（后端 decodeRoleIds 逐个 decodeId）；序列化成字符串就不是数组了
    expect(post.request.body).toEqual({
      username: 'ops2',
      password: 'Abcd1234',
      real_name: '运营乙',
      role_ids: ['ROLE1'],
    });
    post.flush({ code: 0, message: '创建成功', data: { id: 'NEWHASH' } });
    await flushReload();
    await done;
  });

  it('编辑：createOnly 的 username/password/role_ids 不进编辑表单，脱敏的 phone/email 不动就不提交', async () => {
    const p = build();
    await loadList(p, [OTHER]);
    await p.run(p.rows()[0]!, 'edit');

    // password 不在编辑表单里 ⇒ 改密码只能走行内的「重置密码」（那条路带 admin_password 二次确认）
    expect(p.formFields().map((f) => f.name)).toEqual(['real_name', 'phone', 'email']);

    const done = p.submit({
      real_name: '运营甲改',
      phone: OTHER.phone,
      email: OTHER.email,
      role_ids: ['ROLE1'],
    });
    const put = http.expectOne((r) => r.method === 'PUT');
    expect(url(put)).toBe('/admin/v1/user/OTHERHASH');
    // 只有 real_name 变了：脱敏串原样不动 ⇒ 判等相等 ⇒ 不发，否则会把「138****8888」写回库里
    expect(put.request.body).toEqual({ real_name: '运营甲改' });
    put.flush({ code: 0, message: 'ok', data: {} });
    await flushReload();
    await done;
  });

  it('分配角色：role_ids 恒发，**空数组也照发**（清空角色 = sync([])，键没了后端就整段跳过）', async () => {
    const p = build();
    await loadList(p, [OTHER]);
    await p.run(p.rows()[0]!, 'grant');
    // 开弹框是纯前端动作：一个请求都不该发
    await tick();
    http.expectNone(() => true);

    // 选项来自 /admin/v1/role，值必须是 hashid（后端 decodeRoleIds 非 hashid 直接 400）
    const f = p.actFields().find((x) => x.name === 'role_ids')!;
    expect(f.options!.map((o) => o.value)).toEqual(['ROLE1']);

    const done = p.submitAct({ role_ids: [] });
    const put = http.expectOne((r) => r.method === 'PUT');
    expect(url(put)).toBe('/admin/v1/user/OTHERHASH');
    expect(put.request.body).toEqual({ role_ids: [] });
    expect(put.request.detectContentTypeHeader()).toBe('application/json');
    expect(put.request.serializeBody()).toBe('{"role_ids":[]}');
    // 走 JSON 才留得住这个键（JSON.stringify 往返 = 服务端 json_decode 之后拿到的东西）；
    // urlencoded 下空数组编码不出内容、键会整个消失，于是「清空角色」静默失效
    expect(JSON.parse(JSON.stringify(put.request.body))).toEqual({ role_ids: [] });
    put.flush({ code: 0, message: 'ok', data: {} });
    await flushReload();
    await done;
  });

  it('重置密码：PUT 同址，body 同时带 password（新密码）与 admin_password（操作者密码）', async () => {
    const p = build();
    await loadList(p, [OTHER]);
    await p.run(p.rows()[0]!, 'reset');

    const done = p.submitAct({ password: 'NewPass123', admin_password: 'MyOwn456' });
    const put = http.expectOne((r) => r.method === 'PUT');
    expect(url(put)).toBe('/admin/v1/user/OTHERHASH');
    // 两个名字都得在、且没被对调：`admin_password` 缺了服务端回 422「需要密码确认」，
    // 对调了就等于拿新密码去验当前操作者身份 —— 两种都改不了密码，且都不报错到看得懂
    expect(put.request.body).toEqual({ password: 'NewPass123', admin_password: 'MyOwn456' });
    put.flush({ code: 0, message: 'ok', data: {} });
    await flushReload();
    await done;
  });

  it('自我保护：停用当前登录的自己被挡在发请求之前', async () => {
    const p = build();
    const me = { ...ME, status: 1, role_ids: [] };
    await loadList(p, [me, OTHER]);

    await p.run(p.rows()[0]!, 'toggle');
    await tick();
    http.expectNone(() => true);
    // 语言钉在 en ⇒ 断言英文成品（词条 admin.self_guard）
    expect(p.error()).toBe('You cannot disable or delete the admin account you are signed in with');
  });

  it('自我保护：删除自己同样被挡，连二次确认都不弹', async () => {
    const p = build();
    await loadList(p, [{ ...ME, status: 1 }]);

    await p.run(p.rows()[0]!, 'delete');
    await tick();
    http.expectNone(() => true);
    expect(confirmSpy).not.toHaveBeenCalled();
    // 语言钉在 en ⇒ 断言英文成品（词条 admin.self_guard）
    expect(p.error()).toBe('You cannot disable or delete the admin account you are signed in with');
  });

  it('对别人照常：删除先二次确认，再 DELETE /user/{hashid} 且 body 带 password', async () => {
    const p = build();
    await loadList(p, [OTHER]);

    const done = p.run(p.rows()[0]!, 'delete');
    expect(String(confirmSpy.mock.calls[0]![0])).toContain('ops');
    const del = http.expectOne((r) => r.method === 'DELETE');
    expect(url(del)).toBe('/admin/v1/user/OTHERHASH');
    // destroy 走 confirmPassword；密码放 body 而非 query（OperationLog 按字段名过滤敏感字段）
    expect(del.request.body).toEqual({ password: 'pwd' });
    del.flush({ code: 0, message: 'ok', data: {} });
    await flushReload();
    await done;
    expect(p.error()).toBe('');
  });
});
