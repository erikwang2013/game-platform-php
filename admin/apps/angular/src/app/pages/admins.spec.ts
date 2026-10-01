/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { ComponentFixture, TestBed } from '@angular/core/testing';
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
    open(row: Row): Promise<void>;
    info(): { label: string; value: string | number }[];
    detail(): Row | null;
    pickable(row: Row): boolean;
    batch(enable: boolean): Promise<void>;
    note(): string;
    noteErr(): boolean;
    openCreate(): void;
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

  /** 勾选态是**受控**信号（勾选列在 ui-table 里），测试侧直接置位＝用户在表里勾了这几个 */
  const pick = (p: A, ids: string[]): void =>
    (p as unknown as { picked: { set(v: string[]): void } }).picked.set(ids);

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

  /**
   * 密码框点「取消」（原生 prompt 回 null）⇒ **中止**，一个请求都不发、一条错误都不弹。
   *
   * 改前这里写的是 `prompt(...) ?? ''`：null 被吞成空串，DELETE 照发、body 是 `{"password":""}`。
   * 真机实测载荷就是这个（/tmp/ux_probe9.mjs 的 U1，两棵树对同一动作语义相反：react 正确中止）。
   * 后果不是「多一个失败请求」：界面刚告诉操作者「已取消」，紧接着又弹一条 422
   * （confirmPassword 守卫），**无从判断到底删没删**。
   */
  it('密码框点「取消」⇒ 中止，DELETE 一个字节都不发（不是拿空密码照发）', async () => {
    const p = build();
    await loadList(p, [OTHER]);

    promptSpy.mockReturnValue(null);
    await p.run(p.rows()[0]!, 'delete');
    await tick();

    http.expectNone(() => true);
    // 取消是**用户的意图**，不是失败 ⇒ 列表级 error 也要保持干净
    expect(p.error()).toBe('');
  });

  // ---------- 详情抽屉（GET /admin/v1/user/{hashid}） ----------

  it('详情：GET /user/{hashid}，取到就覆盖抽屉；字符串字段原样，不是「ops 0」', async () => {
    const p = build();
    await loadList(p, [OTHER]);

    const done = p.open(p.rows()[0]!);
    // 先摆列表行（抽屉立刻有内容），请求照发
    expect(p.detail()!['username']).toBe('ops');

    http
      .expectOne((r) => r.method === 'GET' && url(r) === '/admin/v1/user/OTHERHASH')
      .flush({ code: 0, message: 'ok', data: { id: 'OTHERHASH', username: 'ops', status: 1 } });
    await done;

    // 详情是「有什么显示什么」：kvOf 对单条记录原样取值。走 pairs() 的话（标量过 num()）
    // 这里会是 { label: 'Username', value: 'ops 0' } —— 数字恰好对，所以这个错不像错。
    // **上面这段理由在本条改成译文之后照样成立**：pairs() 错在**值**那一半（标量被 num()
    // 折成 0、多个标量被拼成一个串），与标签翻不翻没有关系 ⇒ 这条用例必须继续是「值逐字
    // 相等」的写法，谁要把它简化成只看 label，就等于把这条唯一的牙齿拔了。
    // 标签这一半走 kvLabel（`col.<字段名>` 词条）⇒ 是译文，不再是裸列名；
    // `status` 的值仍是**数字 1**（不是 '1'）：字符串化就是这条用例要挡的那个错。
    expect(p.info()).toEqual([
      { label: 'ID', value: 'OTHERHASH' },
      { label: 'Username', value: 'ops' },
      { label: 'Status', value: 1 },
    ]);
  });

  it('详情取不到：留着列表行，不报错、不阻塞抽屉（list 的 error 也不该被点亮）', async () => {
    const p = build();
    await loadList(p, [OTHER]);

    const done = p.open(p.rows()[0]!);
    http
      .expectOne((r) => r.method === 'GET' && url(r) === '/admin/v1/user/OTHERHASH')
      .flush({ code: 404, message: 'not found', data: null }, { status: 404, statusText: 'Not Found' });
    await done;

    expect(p.detail()!['username']).toBe('ops');
    expect(p.error()).toBe('');
  });

  // ---------- 批量启停（POST /admin/v1/user/batch/status） ----------

  it('pickable：自己那行不可勾（后端 batchStatus 没有自我保护），别人可以', async () => {
    const p = build();
    const me = { ...ME, status: 1 };
    await loadList(p, [me, OTHER]);
    // 表格外的成员按 id 判：同一串 hashid 出自同一个 hashids 连接（见 isSelf 的注释）
    expect(p.pickable(p.rows()[0]!)).toBe(false);
    expect(p.pickable(p.rows()[1]!)).toBe(true);
  });

  it('一个都没勾：不发请求、连确认框都不弹', async () => {
    const p = build();
    await loadList(p, [OTHER]);
    await p.batch(true);
    await tick();
    http.expectNone(() => true);
    expect(confirmSpy).not.toHaveBeenCalled();
  });

  it('确认框取消：不发请求', async () => {
    const p = build();
    await loadList(p, [OTHER]);
    confirmSpy.mockReturnValue(false);
    pick(p, ['OTHERHASH']);
    await p.batch(true);
    await tick();
    http.expectNone(() => true);
  });

  it('批量启用：确认文案带动作名与条数，POST {ids,status:1}，成功后清勾选 + 显示服务端 message', async () => {
    const p = build();
    await loadList(p, [OTHER]);
    pick(p, ['OTHERHASH', 'X2']);

    const done = p.batch(true);
    // 语言钉在 en ⇒ 断言英文成品：{action} 是按钮名、{count} 是勾选数，两个占位符都得落上
    expect(String(confirmSpy.mock.calls[0]![0])).toBe(
      'Apply "Enable" to the 2 selected admin accounts?',
    );

    const post = http.expectOne((r) => r.method === 'POST');
    expect(url(post)).toBe('/admin/v1/user/batch/status');
    // status 是**数字 0/1**（后端 in_array($status,[0,1,'0','1'],true)）——不是布尔、不是字符串
    expect(post.request.body).toEqual({ ids: ['OTHERHASH', 'X2'], status: 1 });
    post.flush({ code: 0, message: 'Bulk action Enable succeeded', data: { count: 2 } });
    await flushReload();
    await done;

    // 回执只显示服务端 message：data.count 是 MySQL 的 changed rows，当数量读是错的
    expect(p.note()).toBe('Bulk action Enable succeeded');
    expect(p.noteErr()).toBe(false);
    await tick();
    http.expectNone(() => true); // 勾选已清空（界面上不留一排已勾但已生效的框）
  });

  it('批量停用：同一个端点，status 是数字 0（按钮名进确认文案）', async () => {
    const p = build();
    await loadList(p, [OTHER]);
    pick(p, ['OTHERHASH']);

    const done = p.batch(false);
    expect(String(confirmSpy.mock.calls[0]![0])).toBe(
      'Apply "Disable" to the 1 selected admin accounts?',
    );
    const post = http.expectOne((r) => r.method === 'POST');
    expect(post.request.body).toEqual({ ids: ['OTHERHASH'], status: 0 });
    post.flush({ code: 0, message: 'Bulk action Disable succeeded', data: { count: 0 } });
    await flushReload();
    await done;
    expect(p.note()).toBe('Bulk action Disable succeeded');
  });

  it('批量失败：错误进回执横幅（noteErr），**勾选保留**（还好重试），表格不打错误态', async () => {
    const p = build();
    await loadList(p, [OTHER]);
    pick(p, ['OTHERHASH']);

    const done = p.batch(true);
    http
      .expectOne((r) => r.method === 'POST')
      .flush({ code: 422, message: 'ids 不能为空', data: null }, { status: 422, statusText: 'Unprocessable Content' });
    await done;

    expect(p.noteErr()).toBe(true);
    expect(p.note()).toContain('ids');
    // 失败是**动作结果**不是列表加载失败：整张表还得能用，勾选也得留着（好就地重试）
    expect(p.error()).toBe('');
  });

  // ---------- 渲染级：勾选列 / 抽屉 / 回执横幅的模板分支 ----------
  // 上面那批断的是方法，绕不过模板里的分支（勾选列摆没摆、按钮什么时候禁用、
  // 行点击到底开不开抽屉）—— 这几条只有渲染出来才看得见。

  const el = (f: ComponentFixture<Admins>): HTMLElement => f.nativeElement as HTMLElement;
  const texts = (f: ComponentFixture<Admins>, sel: string): string[] =>
    [...el(f).querySelectorAll(sel)].map((n) => n.textContent!.trim());
  const boxes = (f: ComponentFixture<Admins>): HTMLInputElement[] => [
    ...el(f).querySelectorAll<HTMLInputElement>('tbody td.pick input'),
  ];
  const btns = (f: ComponentFixture<Admins>): HTMLButtonElement[] => [
    ...el(f).querySelectorAll<HTMLButtonElement>('.row-actions button'),
  ];

  /** 渲染整页：createComponent 后首屏两个 GET 都要应答 */
  const render = async (rows: Row[]): Promise<ComponentFixture<Admins>> => {
    const f = TestBed.createComponent(Admins);
    f.detectChanges();
    http
      .expectOne((r) => r.method === 'GET' && url(r) === '/admin/v1/user')
      .flush({ code: 0, message: 'ok', data: { list: rows, total: rows.length } });
    http
      .expectOne((r) => r.method === 'GET' && url(r) === '/admin/v1/role')
      .flush({ code: 0, message: 'ok', data: { list: ROLES, total: ROLES.length } });
    await tick();
    f.detectChanges();
    return f;
  };

  /**
   * ④ 审计实测（真 Chrome）：空表单 `requestSubmit()` 真把 POST /admin/v1/user 发出去了 ——
   * 6 个字段一个 `required` 都没有，界面上只有一枚装饰性 `.req` 星号；而 username 的规则
   * 文案（27 字）塞在 placeholder 里，单列净宽 ~207px ⇒ 被截成
   * 「3-50 characters; cannot be changed aft」，输入框上既没有 title 也没有 aria-label。
   * 两处一起修：required 落控件、规则搬 hint（同文件的 phone/email 早就在用 hint）。
   */
  it('④ 新建表单：required 落在原生 input 上，规则文案在 hint 里而不是被截断的 placeholder 里', async () => {
    const f = await render([]);
    (f.componentInstance as unknown as A).openCreate();
    f.detectChanges();

    const box = (n: string): HTMLInputElement =>
      el(f).querySelector<HTMLInputElement>(`.modal input[name="${n}"]`)!;
    // 三个必填字段都带原生 required ⇒ 空着点提交浏览器当场拦下，不再白跑一个来回
    expect([box('username').required, box('password').required, box('real_name').required]).toEqual([
      true,
      true,
      true,
    ]);
    // 规则文案整句挪到框下的 hint（能换行、能读完），placeholder 留空
    expect([
      box('username').placeholder,
      box('password').placeholder,
      box('real_name').placeholder,
    ]).toEqual(['', '', '']);
    const hints = texts(f, '.modal .hint');
    expect(hints.some((h) => h.startsWith('3-50 characters'))).toBe(true);
    expect(hints.some((h) => h.startsWith('8-32 characters'))).toBe(true);
  });

  /**
   * ① 审计实测：新建弹框的 role_ids 是个 **0 选项的空多选框** —— 候选只在 grantFields()
   * （「分配角色」）里注入过，ADMIN_FIELDS 里那条 role_ids 压根没接。运营能点「+ 新建」，
   * 也能当「角色先不选」提交，可实际上根本无从选择。
   *
   * 钉子按**与服务端一致**钉：回包几个就几个，不写死数字（这条用例的回包 3 个，
   * 与文件顶部 ROLES 的 1 个刻意不同，写死任何一个都会当场露馅）。选项值与文案也逐条对回包 ——
   * 只数个数的话，hashid 串错位、顺序错也能绿。
   */
  it('① 新建弹框：role_ids 的选项来自 /admin/v1/role，条数与服务端回包一致（改前一个都没有）', async () => {
    const roles = [
      { id: 'ROLE_A', name: '超管', slug: 'super' },
      { id: 'ROLE_B', name: '运营', slug: 'ops' },
      { id: 'ROLE_C', name: '财务', slug: 'finance' },
    ];
    const f = TestBed.createComponent(Admins);
    f.detectChanges();
    http
      .expectOne((r) => r.method === 'GET' && url(r) === '/admin/v1/user')
      .flush({ code: 0, message: 'ok', data: { list: [], total: 0 } });
    http
      .expectOne((r) => r.method === 'GET' && url(r) === '/admin/v1/role')
      .flush({ code: 0, message: 'ok', data: { list: roles, total: roles.length } });
    await tick();
    f.detectChanges();

    // 开弹框是纯前端动作（不该发请求）；角色候选是首屏那批 Promise.all 里就取回来的
    (f.componentInstance as unknown as A).openCreate();
    f.detectChanges();

    const sel = el(f).querySelector<HTMLSelectElement>('.modal select[name="role_ids"]')!;
    const opts = [...sel.options];
    expect(opts.map((o) => o.value)).toEqual(roles.map((r) => r.id));
    expect(opts.map((o) => o.textContent!.trim())).toEqual(roles.map((r) => r.name));
    expect(opts.length).toBe(roles.length);
  });

  it('勾选列：表头是译文不是裸键；自己那行的框禁用（点不动，也不会被勾上）', async () => {
    const f = await render([{ ...ME, status: 1 }, OTHER]);

    expect(texts(f, 'thead th.pick')).toEqual(['Select']);
    expect(el(f).textContent).not.toContain('table.pick');
    const [mine, other] = boxes(f);
    expect(mine!.disabled).toBe(true);
    expect(other!.disabled).toBe(false);

    mine!.click();
    await tick();
    f.detectChanges();
    expect(mine!.checked).toBe(false);
    expect(texts(f, '.row-actions .sub')).toEqual(['0 selected']);
  });

  it('勾选不弹详情：框上的 stopPropagation 挡住行点击（行点击是开抽屉）', async () => {
    const f = await render([OTHER]);
    boxes(f)[0]!.click();
    await tick();
    f.detectChanges();

    expect(el(f).querySelector('.drawer')).toBeNull();
    expect(texts(f, '.row-actions .sub')).toEqual(['1 selected']);
    // 选中之前两个按钮都是死的（空提交会被后端 422「ids 不能为空」弹回来）
    expect(btns(f).map((b) => b.disabled)).toEqual([false, false]);
    expect(texts(f, '.row-actions button')).toEqual(['Enable', 'Disable']);
  });

  it('未勾选时批量按钮禁用；行点击开抽屉，标题是译文、内容来自详情端点', async () => {
    const f = await render([OTHER]);
    expect(btns(f).map((b) => b.disabled)).toEqual([true, true]);

    el(f).querySelector<HTMLElement>('tbody tr')!.click();
    await tick();
    f.detectChanges();
    http
      .expectOne((r) => r.method === 'GET' && url(r) === '/admin/v1/user/OTHERHASH')
      .flush({ code: 0, message: 'ok', data: { username: 'ops', real_name: '运营甲' } });
    await tick();
    f.detectChanges();

    expect(texts(f, '.drawer header b')).toEqual(['Details']);
    // 标签走 kvLabel。改前这里钉的是 ['username','real_name']（裸列名），那**编码的是缺陷本身**
    // —— 抽屉把数据库字段名摆给运营看；值那一半（下一行 .kv dd）一个字没动。
    expect(texts(f, '.kv dt')).toEqual(['Username', 'Real Name']);
    expect(texts(f, '.kv dd')).toEqual(['ops', '运营甲']);
  });

  it('批量提交：成功回执进绿色横幅（.notice 不是 .alert），勾选清零、按钮回到禁用', async () => {
    const f = await render([OTHER]);
    boxes(f)[0]!.click();
    await tick();
    f.detectChanges();

    btns(f)[0]!.click();
    await tick();
    const post = http.expectOne((r) => r.method === 'POST');
    expect(url(post)).toBe('/admin/v1/user/batch/status');
    expect(post.request.body).toEqual({ ids: ['OTHERHASH'], status: 1 });
    post.flush({ code: 0, message: 'Bulk action Enable succeeded', data: { count: 1 } });
    // 回读仍回这一行（flushReload 给的是空列表 ⇒ 批量条会整条消失，断言就落空了）
    await tick();
    http
      .expectOne((r) => r.method === 'GET' && url(r) === '/admin/v1/user')
      .flush({ code: 0, message: 'ok', data: { list: [OTHER], total: 1 } });
    http
      .expectOne((r) => r.method === 'GET' && url(r) === '/admin/v1/role')
      .flush({ code: 0, message: 'ok', data: { list: ROLES, total: ROLES.length } });
    await tick();
    f.detectChanges();

    expect(texts(f, '.notice')).toEqual(['Bulk action Enable succeeded']);
    expect(el(f).querySelector('.alert')).toBeNull();
    expect(texts(f, '.row-actions .sub')).toEqual(['0 selected']);
    expect(btns(f).map((b) => b.disabled)).toEqual([true, true]);
  });
});
