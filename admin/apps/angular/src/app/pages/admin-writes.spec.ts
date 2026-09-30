/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { Row } from '../core/api.service';
import { Crud } from '../core/crud';
import { Settings } from './settings';
import { Support } from './support';
import { Users } from './users';

/**
 * 用户与权限批次（角色 / 权限 / 实名审核 / 工单）的写操作接线钉子。
 * 钉的是**端点串 + HTTP 方法 + 请求体形状**，不是「调了某个方法」——
 * 上一批的教训：护栏只看函数名不看实参，端点打错（/role/list、把 hashid 递给 (int)）照样绿。
 */
describe('管理端写操作接线', () => {
  let http: HttpTestingController;
  let confirmSpy: ReturnType<typeof vi.spyOn>;
  let promptSpy: ReturnType<typeof vi.spyOn>;

  /** 让被测代码跑过 await 的微任务，好让它发出的下一个请求进到 HttpTestingController */
  const tick = (): Promise<void> => new Promise((resolve) => setTimeout(resolve, 0));

  beforeEach(() => {
    TestBed.configureTestingModule({ providers: [provideHttpClient(), provideHttpClientTesting()] });
    http = TestBed.inject(HttpTestingController);
    confirmSpy = vi.spyOn(globalThis, 'confirm').mockReturnValue(true);
    promptSpy = vi.spyOn(globalThis, 'prompt').mockReturnValue('');
  });

  afterEach(() => {
    confirmSpy.mockRestore();
    promptSpy.mockRestore();
    try {
      http.verify();
    } finally {
      // 必须显式复位：否则用例失败留下的未决请求会污染下一个用例的 configureTestingModule
      TestBed.resetTestingModule();
    }
  });

  /** 页面方法多是 protected，测试侧按鸭子类型取用；inject(Api) 在字段初始化器里 ⇒ 必须有注入上下文 */
  const build = <T>(make: () => T): T => TestBed.runInInjectionContext(make);
  /** TestRequest 上挂着 .request，而 expectOne 回调拿到的是 HttpRequest 本身 —— 两种都收 */
  const url = (x: { request: { url: string } } | { url: string }): string =>
    ('request' in x ? x.request.url : x.url).split('?')[0]!;

  describe('Settings 角色 / 权限', () => {
    type S = {
      tab: { set(v: string): void };
      crud(): Crud | null;
      load(): Promise<void>;
      run(row: Row, key: string): Promise<void>;
      submit(values: Row): Promise<void>;
      formError(): string;
      rows(): Row[];
    };

    /** 权限树：角色标签页也要它（permission_ids 的值域），返回整棵嵌套树 */
    const flushTree = async (): Promise<void> => {
      http.expectOne((r) => r.method === 'GET' && url(r) === '/admin/v1/permission').flush({
        code: 0,
        message: 'ok',
        data: [
          {
            id: 'PERM1',
            name: '系统',
            slug: 'system',
            type: 1,
            children: [
              { id: 'PERM2', name: '用户', slug: 'user', type: 2, children: [] },
              // 子节点带 children: [] —— buildTree 会照发，摊平不能因此多出空行
            ],
          },
        ],
      });
      await tick();
    };

    it('角色：列表走 /admin/v1/role（Route::resource，没有 /role/list），增删改同址 + hashid', async () => {
      const s = build(() => new Settings()) as unknown as S;
      s.tab.set('role');
      const done = s.load();
      // 逐字对 /role/list 这个不存在的路径：打上去就是 404 信封，而列表页会显示「暂无数据」
      const req = http.expectOne((r) => r.method === 'GET' && url(r) === '/admin/v1/role');
      req.flush({ code: 0, message: 'ok', data: { list: [{ id: 'R1', name: '运营' }], total: 1 } });
      await flushTree();
      await done;

      expect(s.rows().length).toBe(1);
      const c = s.crud()!;
      expect(c.ends.create).toBe('/admin/v1/role');
      // ends 的写端点改成了可缺省（缺省即没有该能力）⇒ 取值要过 `?.`；
      // 断言强度不变：端点串错了照样红（undef !== '/admin/v1/role/R1'）
      expect(c.ends.update?.('R1')).toBe('/admin/v1/role/R1');
      expect(c.ends.remove?.('R1')).toBe('/admin/v1/role/R1');
      // destroy 有 confirmPassword 守卫 ⇒ 删除必须带密码框
      expect(c.deletePassword).toBe(true);
      // 字段真值 = RoleController::store/update 的 validator；slug 只在 store ⇒ 编辑态不应出现
      expect(c.fields.map((f) => f.name)).toEqual([
        'name',
        'slug',
        'description',
        'permission_ids',
      ]);
      expect(c.statused).toBe(true);
    });

    it('角色权限：多选值域 = 权限树 hashid（裸 id 后端会 400），编辑态按行回填且只发改动的字段', async () => {
      const s = build(() => new Settings()) as unknown as S;
      s.tab.set('role');
      const done = s.load();
      http.expectOne((r) => r.method === 'GET' && url(r) === '/admin/v1/role').flush({
        code: 0,
        message: 'ok',
        data: {
          list: [{ id: 'R1', name: '运营', slug: 'ops', permission_ids: ['PERM2'] }],
          total: 1,
        },
      });
      await flushTree();
      await done;

      const f = s.crud()!.fields.find((x) => x.name === 'permission_ids')!;
      expect(f.type).toBe('multi');
      // 值必须是节点 hashid —— decodePermissionIds() 非 hashid 直接 400，摆裸数字落不了库
      expect(f.options!.map((o) => o.value)).toEqual(['PERM1', 'PERM2']);
      // 标签带层级缩进（U+3000，trim 能去掉），子节点看得出来是谁的下级
      expect(f.options![1]!.label.trim()).toBe('用户');
      expect(f.options![1]!.label.length).toBeGreaterThan(f.options![0]!.label.length);

      // 编辑态：只改权限 ⇒ 请求体里只有 permission_ids，且必须是**数组**（不是 JSON 字符串，
      // 后端 sync() 收到字符串就不是数组了）；slug 是 createOnly，改名不该带上；
      // name/description 与旧值相同也不该发（局部更新）
      await s.run(s.rows()[0]!, 'edit');
      const done2 = s.submit({
        name: '运营',
        slug: 'ops',
        description: '',
        permission_ids: ['PERM1', 'PERM2'],
      });
      const put = http.expectOne((r) => r.method === 'PUT');
      expect(url(put)).toBe('/admin/v1/role/R1');
      expect(put.request.body).toEqual({ permission_ids: ['PERM1', 'PERM2'] });
      put.flush({ code: 0, message: 'ok', data: [] });
      await tick();
      http
        .expectOne((r) => r.method === 'GET' && url(r) === '/admin/v1/role')
        .flush({ code: 0, message: 'ok', data: { list: [], total: 0 } });
      await flushTree();
      await done2;
      expect(s.formError()).toBe('');
    });

    it('权限：列表走 /admin/v1/permission，且嵌套树被摊平（子节点可见、name 不被改写）', async () => {
      const s = build(() => new Settings()) as unknown as S;
      s.tab.set('permission');
      const done = s.load();
      http.expectOne((r) => r.method === 'GET').flush({
        code: 0,
        message: 'ok',
        data: [
          {
            id: 'P1',
            name: '系统',
            slug: 'system',
            type: 1,
            parent_id: 0,
            sort: 0,
            children: [{ id: 'P2', name: '用户', slug: 'user', type: 2, parent_id: 'P1', sort: 1 }],
          },
        ],
      });
      await done;

      const rows = s.rows();
      expect(rows.length).toBe(2);
      expect(rows[1]!['id']).toBe('P2');
      // 摊平只加 tree 列：name/slug 被改写过的话，编辑一次就会把缩进写回库里
      expect(rows[1]!['name']).toBe('用户');
      expect(rows[1]!['slug']).toBe('user');
      expect(String(rows[1]!['tree'])).not.toBe('');
      // 父级回显：buildTree 的 parent_id 是 hashid，编辑态读不懂它 ⇒ 列表按名字回显
      expect(rows[1]!['parent_name']).toBe('系统');
      expect(rows[0]!['parent_name']).toBe('（根）');

      const c = s.crud()!;
      expect(c.ends.update?.('P2')).toBe('/admin/v1/permission/P2');
      expect(c.deletePassword).toBe(true);
      // parent_id 是 createOnly（update 不收）：选项 = 同一棵树的 hashid
      const f = c.fields.find((x) => x.name === 'parent_id')!;
      expect(f.createOnly).toBe(true);
      expect(f.options!.map((o) => o.value)).toEqual(['P1', 'P2']);
    });

    it('权限：新建时父级发 hashid（不填 = 不提交，落根），裸数字永不出现', async () => {
      const s = build(() => new Settings()) as unknown as S;
      s.tab.set('permission');
      const done = s.load();
      http.expectOne((r) => r.method === 'GET').flush({
        code: 0,
        message: 'ok',
        data: [{ id: 'P1', name: '系统', slug: 'system', type: 1, parent_id: 0, children: [] }],
      });
      await done;

      const done2 = s.submit({
        name: '新建子权限',
        slug: 'sub',
        type: '2',
        parent_id: 'P1',
        icon: '',
        path: '',
        sort: '',
      });
      const post = http.expectOne((r) => r.method === 'POST');
      expect(url(post)).toBe('/admin/v1/permission');
      // 空字段不发：icon/path 空串与「没填」同义，sort 空是 undefined（不发就不会被写成 0）
      expect(post.request.body).toEqual({
        name: '新建子权限',
        slug: 'sub',
        type: '2',
        parent_id: 'P1',
      });
      post.flush({ code: 0, message: 'ok', data: { id: 'P9' } });
      await tick();
      http
        .expectOne((r) => r.method === 'GET')
        .flush({ code: 0, message: 'ok', data: [] });
      await done2;
      expect(s.formError()).toBe('');
    });
  });

  describe('Users 实名审核', () => {
    type U = {
      tab: { set(v: string): void };
      review(row: Row, action: 'approve' | 'reject'): Promise<void>;
      error(): string;
    };
    const row = { id: 'IDHASH1', user: { id: 'UHASH1', username: 'bob' } };

    it('驳回：先二次确认（文案带用户名）再收备注，PUT /admin/v1/identity/review {id,action,note}', async () => {
      const u = build(() => new Users()) as unknown as U;
      u.tab.set('identity');
      promptSpy.mockReturnValue('证件模糊');
      const done = u.review(row, 'reject');

      expect(confirmSpy).toHaveBeenCalled();
      expect(String(confirmSpy.mock.calls[0]![0])).toContain('bob');

      const req = http.expectOne((r) => r.method === 'PUT');
      expect(url(req)).toBe('/admin/v1/identity/review');
      // action 只认 approve|reject（IdentityController::review 的 validator），id 是记录的 hashid
      expect(req.request.body).toEqual({ id: 'IDHASH1', action: 'reject', note: '证件模糊' });
      req.flush({ code: 0, message: 'KYC rejected', data: [] });

      await tick();
      http
        .expectOne((r) => r.method === 'GET')
        .flush({ code: 0, message: 'ok', data: { list: [], total: 0 } });
      await done;
      expect(u.error()).toBe('');
    });

    it('通过：不弹确认也不问备注（备注是驳回才有的东西）', async () => {
      const u = build(() => new Users()) as unknown as U;
      u.tab.set('identity');
      const done = u.review(row, 'approve');

      const req = http.expectOne((r) => r.method === 'PUT');
      expect(req.request.body).toEqual({ id: 'IDHASH1', action: 'approve', note: '' });
      req.flush({ code: 0, message: 'KYC approved', data: [] });

      expect(confirmSpy).not.toHaveBeenCalled();
      expect(promptSpy).not.toHaveBeenCalled();

      await tick();
      http
        .expectOne((r) => r.method === 'GET')
        .flush({ code: 0, message: 'ok', data: { list: [], total: 0 } });
      await done;
    });

    it('取消备注输入（prompt 返回 null）＝ 放弃驳回，一个请求都不发', async () => {
      const u = build(() => new Users()) as unknown as U;
      u.tab.set('identity');
      promptSpy.mockReturnValue(null);
      await u.review(row, 'reject');
      await tick();
      http.expectNone(() => true);
    });
  });

  describe('Support 工单动作', () => {
    type S = {
      openAct(row: Row, act: string): void;
      submitAct(values: Row): Promise<void>;
      closeTicket(row: Row): Promise<void>;
      formError(): string;
    };
    const row = { id: 'THASH1', subject: '充值未到账' };

    it('回复：POST /admin/v1/ticket/{hashid}/reply，正文为空先拦在前端', async () => {
      const s = build(() => new Support()) as unknown as S;
      s.openAct(row, 'reply');
      await s.submitAct({ content: '   ' });
      expect(s.formError()).toContain('不能为空');
      await tick();
      http.expectNone(() => true);

      const done = s.submitAct({ content: '已补单' });
      const req = http.expectOne((r) => r.method === 'POST');
      expect(url(req)).toBe('/admin/v1/ticket/THASH1/reply');
      expect(req.request.body).toEqual({ content: '已补单' });
      req.flush({ code: 0, message: 'Reply sent', data: { id: 'RH1' } });

      await tick();
      http
        .expectOne((r) => r.method === 'GET')
        .flush({ code: 0, message: 'ok', data: { list: [], total: 0 } });
      await done;
    });

    it('指派：数字串原样发（snowflake 19 位超出 JS 安全整数，过 Number 会换人），hashid 直接拦下', async () => {
      const s = build(() => new Support()) as unknown as S;
      s.openAct(row, 'assign');
      // 后端 (int) $request->input('admin_id') 精确；前端 Number('10000000000000001') 会变成 10000000000000000
      const done = s.submitAct({ admin_id: '10000000000000001' });
      const req = http.expectOne((r) => r.method === 'POST');
      expect(url(req)).toBe('/admin/v1/ticket/THASH1/assign');
      expect(req.request.body).toEqual({ admin_id: '10000000000000001' });
      req.flush({ code: 0, message: 'Assigned', data: [] });

      await tick();
      http
        .expectOne((r) => r.method === 'GET')
        .flush({ code: 0, message: 'ok', data: { list: [], total: 0 } });
      await done;

      s.openAct(row, 'assign');
      await s.submitAct({ admin_id: 'XyZ123' });
      expect(s.formError()).toContain('只能填数字');
      await tick();
      http.expectNone(() => true);
    });

    it('关闭：二次确认带标题，确认后 POST /close；取消则不发请求', async () => {
      const s = build(() => new Support()) as unknown as S;
      const done = s.closeTicket(row);
      expect(String(confirmSpy.mock.calls[0]![0])).toContain('充值未到账');
      const req = http.expectOne((r) => r.method === 'POST');
      expect(url(req)).toBe('/admin/v1/ticket/THASH1/close');
      req.flush({ code: 0, message: 'Ticket closed', data: [] });

      await tick();
      http
        .expectOne((r) => r.method === 'GET')
        .flush({ code: 0, message: 'ok', data: { list: [], total: 0 } });
      await done;

      confirmSpy.mockReturnValue(false);
      await s.closeTicket(row);
      await tick();
      http.expectNone(() => true);
    });
  });
});
