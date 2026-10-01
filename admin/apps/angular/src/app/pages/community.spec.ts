/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { DICT } from '../core/i18n/dictionary';
import { use } from '../core/i18n/i18n';
import { Community } from './community';

/**
 * 社群两屏（组队/公会 + 分享裂变统计）的钉子。
 *
 * 断的是**具体端点串与出参形状**，不是「调了某个方法」：这两个端点的坑都在参数上 ——
 * `status=0` 会被「空参不发送」的逻辑误伤，而 `from/to` 留空又必须真的不发（后端只在给了日期
 * 时才加条件，发一个空串过去它照样 `where created_at >= ' 00:00:00'` 那样错过滤）。
 */
describe('Community 社群', () => {
  let http: HttpTestingController;
  let page: Community;

  /** protected 成员按鸭子类型取用（类只从模板调它） */
  const p = <T>(name: string) => (page as unknown as Record<string, T>)[name];
  /** 取类成员并**绑回 this** 再调：`p('apply')()` 是脱了接收者的裸调用，模板里调从来带 this */
  const call = <R>(name: string, ...args: unknown[]): R =>
    (p<(...a: unknown[]) => R>(name)).call(page, ...args);
  /**
   * 当前页签那批表头用的**词条键**。注意 heads() 给的是 `{列名: 词条键}` 映射，
   * 不是 colsOf() 那种 `{key,label}[]`（译名在 ui-table 内部查表，这里要核的正是键本身）。
   */
  const headKeys = (): string[] => Object.values(call<Record<string, string>>('heads'));
  const signalOf = <T>(name: string) => p<{ set(v: T): void; (): T }>(name);

  const tick = (): Promise<void> => new Promise((resolve) => setTimeout(resolve, 0));

  beforeEach(() => {
    use('zh');
    TestBed.configureTestingModule({ providers: [provideHttpClient(), provideHttpClientTesting()] });
    http = TestBed.inject(HttpTestingController);
    page = TestBed.runInInjectionContext(() => new Community());
  });

  afterEach(() => {
    try {
      http.verify();
    } finally {
      TestBed.resetTestingModule();
    }
  });

  /** 首屏：组列表 + 游戏下拉（ngOnInit 里两个都要发） */
  const boot = (): void => {
    page.ngOnInit();
    http.expectOne((r) => r.url.startsWith('/admin/v1/groups')).flush({
      code: 0,
      message: 'ok',
      data: {
        list: [{ id: 'G1', name: 'A队', type: 'team', status: 1, member_count: 3 }],
        total: 1,
      },
    });
    http.expectOne((r) => r.url.startsWith('/admin/v1/game/list')).flush({
      code: 0,
      message: 'ok',
      data: { list: [{ id: 'GA', name: 'GameA' }], total: 1 },
    });
  };

  it('首屏取组列表与游戏下拉：分页别名齐发，空筛选一个都不带', async () => {
    page.ngOnInit();

    const list = http.expectOne((r) => r.method === 'GET' && r.url.startsWith('/admin/v1/groups'));
    // model 读的是 limit（默认 15），page_size 别名必须一起发，否则每页条数与界面对不上
    expect(list.request.url).toContain('page=1');
    expect(list.request.url).toContain('limit=20');
    expect(list.request.url).toContain('page_size=20');
    expect(list.request.url).not.toContain('type=');
    expect(list.request.url).not.toContain('status=');
    expect(list.request.url).not.toContain('game_id=');
    list.flush({ code: 0, message: 'ok', data: { list: [], total: 0 } });

    const games = http.expectOne((r) => r.url.startsWith('/admin/v1/game/list'));
    expect(games.request.url).toContain('limit=200');
    games.flush({ code: 0, message: 'ok', data: { list: [{ id: 'GA', name: 'GameA' }], total: 1 } });
    await tick(); // loadGames 是 await 链，选项要在微任务之后才落进 signal

    expect(call<unknown[]>('rows')).toEqual([]);
    expect(signalOf<unknown[]>('games')()).toEqual([{ id: 'GA', name: 'GameA' }]);
  });

  it('筛选只认「查询」提交的那一份；status=0 必须真的发出去（0 是有效筛选，不是空）', () => {
    boot();

    signalOf<string>('fType').set('team');
    signalOf<string>('fStatus').set('0');
    signalOf<string>('fGame').set('GAME9');
    call('apply');

    const req = http.expectOne((r) => r.url.startsWith('/admin/v1/groups'));
    expect(req.request.url).toContain('type=team');
    expect(req.request.url).toContain('status=0');
    expect(req.request.url).toContain('game_id=GAME9');
    expect(req.request.url).toContain('page=1'); // 换筛选必须回第一页
    req.flush({ code: 0, message: 'ok', data: { list: [], total: 0 } });
  });

  it('行内「成员审计」→ GET /groups/{hashid}/audit，抽屉里是 group + members 两张表', async () => {
    boot();

    call('run', { id: 'G1', name: 'A队' }, 'audit');
    await tick();

    const req = http.expectOne((r) => r.url === '/admin/v1/groups/G1/audit');
    expect(req.request.method).toBe('GET');
    req.flush({
      code: 0,
      message: 'ok',
      data: {
        group: { id: 'G1', name: 'A队', type: 'team', status: 1, member_count: 2 },
        members: [
          { user_id: 'U1', role: 'leader', contrib: 10, joined_at: '2026-09-01 10:00:00', left_at: null },
          { user_id: 'U2', role: 'member', contrib: 0, joined_at: '2026-09-02 10:00:00', left_at: '2026-09-03 10:00:00' },
        ],
      },
    });
    await tick(); // run() 是同步的，它 fire-and-forget 掉的 openAudit 在这里落地

    expect(call<unknown[]>('groupRows')).toEqual([
      { id: 'G1', name: 'A队', type: 'team', status: 1, member_count: 2 },
    ]);
    expect(call<unknown[]>('members')).toHaveLength(2);
    expect(call<string>('error')).toBe('');
  });

  it('审计取不到时只报错、不开抽屉（退回渲染列表行会让人以为「成员是空的」）', async () => {
    boot();

    const done = call<Promise<void>>('openAudit', { id: 'G1' });
    http
      .expectOne((r) => r.url === '/admin/v1/groups/G1/audit')
      .flush({ code: 404, message: 'Group not found' }, { status: 404, statusText: 'Not Found' });
    await done;

    expect(call<string>('error')).toContain('Group not found');
    expect(signalOf<unknown>('audit')()).toBeNull();
  });

  it('切到分享统计：GET /share/stats 且日期留空一个参数都不发；漏斗三项 + 按天表', async () => {
    boot();
    call('pick', 'share');

    const req = http.expectOne((r) => r.url.startsWith('/admin/v1/share/stats'));
    // 留空即全量：空串发过去后端照样会拿它拼 created_at 条件
    expect(req.request.url).toBe('/admin/v1/share/stats');
    req.flush({
      code: 0,
      message: 'ok',
      data: {
        funnel: { shares: 3, clicks: 9, conversions: 1 },
        daily: [{ day: '2026-09-30', shares: 2, clicks: 5, conversions: 1 }],
      },
    });
    await tick();

    expect(call<{ label: string; value: string }[]>('funnel')).toEqual([
      { label: 'community.shares', value: '3' },
      { label: 'community.clicks', value: '9' },
      { label: 'community.conversions', value: '1' },
    ]);
    // 按天表进 rows()（服务端固定 30 行、没有分页参数）
    expect(call<unknown[]>('rows')).toEqual([
      { day: '2026-09-30', shares: 2, clicks: 5, conversions: 1 },
    ]);
  });

  it('分享统计给了日期就发成查询串（from/to 是后端唯一的筛选项）', async () => {
    boot();
    call('pick', 'share');
    http.expectOne((r) => r.url.startsWith('/admin/v1/share/stats')).flush({
      code: 0,
      message: 'ok',
      data: { funnel: {}, daily: [] },
    });
    await tick();

    signalOf<string>('from').set('2026-09-01');
    signalOf<string>('to').set('2026-09-30');
    call('search');

    const req = http.expectOne((r) => r.url.startsWith('/admin/v1/share/stats'));
    expect(req.request.url).toContain('from=2026-09-01');
    expect(req.request.url).toContain('to=2026-09-30');
    req.flush({ code: 0, message: 'ok', data: { funnel: {}, daily: [] } });
  });

  it('只有「成员审计」一个行内动作，且分享页没有动作列；本页没有写端点 ⇒ 不出现新建/编辑/删除', () => {
    boot();
    expect(call<unknown[]>('actions')).toEqual([{ key: 'audit', label: 'community.audit' }]);

    call('pick', 'share');
    http.expectOne((r) => r.url.startsWith('/admin/v1/share/stats')).flush({
      code: 0,
      message: 'ok',
      data: { funnel: {}, daily: [] },
    });
    expect(call<unknown[]>('actions')).toEqual([]);
  });

  it('游戏下拉挂了只是少一个筛选，不让整页报错', async () => {
    page.ngOnInit();
    http.expectOne((r) => r.url.startsWith('/admin/v1/groups')).flush({
      code: 0,
      message: 'ok',
      data: { list: [], total: 0 },
    });
    http
      .expectOne((r) => r.url.startsWith('/admin/v1/game/list'))
      .flush({ code: 500, message: 'boom' }, { status: 500, statusText: 'Server Error' });
    await tick();

    expect(signalOf<unknown[]>('games')()).toEqual([]);
    expect(call<string>('error')).toBe('');
  });

  it('表头与动作引用的词条键都在词典里（拼错的键会原样显示成 col.xxx）', () => {
    boot();
    const used = [
      ...headKeys(),
      ...Object.values(p<Record<string, string>>('auditHeads')),
      ...Object.values(p<Record<string, string>>('memberHeads')),
      ...call<{ key: string; label: string }[]>('actions').map((a) => a.label),
      ...p<{ label: string }[]>('tabs').map((t) => t.label),
      ...p<{ label: string }[]>('TYPES').map((t) => t.label),
    ];
    const missing = used.filter((k) => !(k in DICT));
    expect(missing).toEqual([]);
    // 抽到的 ja 里 col.role 那几条是手写的，顺带钉住「不是照抄英文」
    expect(DICT['community.audit']?.[1]).toBe('成员审计');
    expect(DICT['col.member_count']?.[0]).toBe('Member Count');
  });
});
