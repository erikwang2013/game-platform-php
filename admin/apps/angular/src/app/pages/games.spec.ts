/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { Field } from '../core/crud';
import { DICT } from '../core/i18n/dictionary';
import { use } from '../core/i18n/i18n';
import { Games } from './games';

/**
 * 游戏页两个行内动作的钉子（游戏币种 / 分配游戏）。
 *
 * 两个端点的坑都不在「调没调」，而在**参数形状**上：`currencies` 收的是**数组**
 * （`required|array`，把 JSON 文本原样发过去必被 validator 打回），`game_ids` 收的是按行切的数组；
 * 而币种那份数组**必须带着 id 回传** —— 抹掉 id 后端就当新建，一次提交把整张币种表复制成重复行
 * （`manageCurrency` 按 id 有无分派 update/create，且**从不删除**不在表里的行）。
 *
 * ⚠ 行内动作跑完基类还会 `await this.load()`（crud.ts 的 run 末尾）⇒ 每个动作用例都多一跳列表请求，
 * 不 flush 就等着超时。
 */
describe('Games 游戏页', () => {
  let http: HttpTestingController;
  let page: Games;

  /** protected 成员按鸭子类型取用（类只从模板调它） */
  const p = <T>(name: string) => (page as unknown as Record<string, T>)[name];
  /** 取类成员并**绑回 this** 再调：`p('pick')()` 是脱了接收者的裸调用，模板里调从来带 this */
  const call = <R>(name: string, ...args: unknown[]): R =>
    (p<(...a: unknown[]) => R>(name)).call(page, ...args);
  const signalOf = <T>(name: string) => p<{ set(v: T): void; (): T }>(name);

  const tick = (): Promise<void> => new Promise((resolve) => setTimeout(resolve, 0));

  beforeEach(() => {
    use('zh');
    TestBed.configureTestingModule({ providers: [provideHttpClient(), provideHttpClientTesting()] });
    http = TestBed.inject(HttpTestingController);
    page = TestBed.runInInjectionContext(() => new Games());
  });

  afterEach(() => {
    try {
      http.verify();
    } finally {
      TestBed.resetTestingModule();
    }
  });

  const LIST: Record<string, unknown[]> = {
    game: [{ id: 'G1', name: 'GameA', status: 1 }],
    category: [{ id: 'C1', name: '分类A', status: 1 }],
  };

  const listReq = (tab: string) =>
    http.expectOne((r) => r.url.startsWith(`/admin/v1/game/${tab === 'game' ? '' : tab + '/'}list`));

  /** 首屏（或切页签后）那张列表：flush 掉再等 load() 的 await 链落地 */
  const flushList = async (tab: string): Promise<void> => {
    listReq(tab).flush({ code: 0, message: 'ok', data: { list: LIST[tab], total: 1 } });
    await tick();
  };

  /**
   * 打开游戏币种弹框：GET 取现值 → 开框 → 基类补的那跳列表请求，全走完（含 await run）。
   */
  const openCurrency = async (row: Record<string, unknown> = { id: 'G1', name: 'GameA' }): Promise<void> => {
    const done = call<Promise<void>>('run', row, 'currency');
    const req = http.expectOne((r) => r.url === '/admin/v1/game/G1');
    expect(req.request.method).toBe('GET'); // 现值必须是**查**出来的，不是从列表行猜的
    req.flush({
      code: 0,
      message: 'ok',
      data: {
        id: 'G1',
        name: 'GameA',
        currencies: [
          { id: 'CU1', name: '金币', symbol: 'GC', exchange_rate: '1.0000', spread_pct: '0.00' },
        ],
      },
    });
    await tick();
    await flushList('game'); // run() 里 extra 之后那句 load()
    await done;
  };

  it('首屏取游戏列表：分页别名齐发', async () => {
    page.ngOnInit();
    const req = listReq('game');
    expect(req.request.method).toBe('GET');
    expect(req.request.url).toContain('page=1');
    expect(req.request.url).toContain('page_size=20');
    req.flush({ code: 0, message: 'ok', data: { list: LIST['game'], total: 1 } });
    await tick();
    expect(call<unknown[]>('rows')).toHaveLength(1);
  });

  it('动作列：三个页签各挂各的（币种只在游戏、分配只在分类、区服一个都没有）', async () => {
    page.ngOnInit();
    await flushList('game');
    const keys = (): string[] => call<{ key: string }[]>('actions').map((a) => a.key);
    // 游戏：编辑 + 删除 + 启用/停用 + 币种
    expect(keys()).toEqual(['edit', 'delete', 'toggle', 'currency']);

    call('pick', 'category');
    await flushList('category');
    // 分类：**没有** currency（EXTRAS 按页签取），只有自己的 assign
    expect(keys()).toEqual(['edit', 'delete', 'toggle', 'assign']);

    call('pick', 'server');
    await tick(); // 没填 game_id ⇒ 这一跳不发请求
    expect(keys()).toEqual(['edit', 'delete']); // statused=false：区服的 0..3 不能用基类的 0/1 翻转
  });

  it('区服页签没填 game_id 时一个请求都不发（端点 required，空手去只会换回 422）', async () => {
    page.ngOnInit();
    await flushList('game');
    call('pick', 'server');
    await tick();
    // 没有 expectOne：一个请求都没发才过得了 afterEach 的 verify
    expect(call<boolean>('needGameId')).toBe(true);
    expect(call<unknown[]>('rows')).toEqual([]);
  });

  it('游戏币种：先 GET 详情取现值，回填的 JSON **带 id**（id 丢了后端当新建）', async () => {
    page.ngOnInit();
    await flushList('game');
    await openCurrency();

    const v = call<{ currencies: string } | null>('actValue');
    expect(v).not.toBeNull();
    const arr = JSON.parse(String(v!.currencies));
    expect(Array.isArray(arr)).toBe(true);
    expect(arr[0].id).toBe('CU1'); // ← 全部意义所在：id 在，才是「改这一条」
    expect(arr[0].name).toBe('金币');
    expect(signalOf<boolean>('actOpen')()).toBe(true);
    expect(call<string>('actTitle')).toBe('game.currency_act');
  });

  it('币种取不到现值：只报错、**不开框**（给个 id 全丢的空框等于批量复制币种行）', async () => {
    page.ngOnInit();
    await flushList('game');

    const done = call<Promise<void>>('run', { id: 'G1', name: 'GameA' }, 'currency');
    http
      .expectOne((r) => r.url === '/admin/v1/game/G1')
      .flush({ code: 404, message: 'Game not found' }, { status: 404, statusText: 'Not Found' });
    await done;

    expect(signalOf<boolean>('actOpen')()).toBe(false);
    // run() 里 extra 抛错走 catch，后面那句 load() 不执行 ⇒ 提示不会被列表刷新抹掉
    expect(call<string>('error')).toContain('Game not found');
  });

  it('币种提交：POST /game/currency/manage，body 里是**数组**不是字符串，game_id 是行 id', async () => {
    page.ngOnInit();
    await flushList('game');
    await openCurrency();

    const text = '[\n  {"id":"CU1","name":"金币","exchange_rate":"1.5","spread_pct":"2"}\n]';
    const post = call<Promise<void>>('submitAct', { currencies: text });
    const req = http.expectOne((r) => r.url === '/admin/v1/game/currency/manage');
    expect(req.request.method).toBe('POST');
    expect(req.request.body).toEqual({
      game_id: 'G1',
      currencies: [{ id: 'CU1', name: '金币', exchange_rate: '1.5', spread_pct: '2' }],
    });
    req.flush({ code: 0, message: 'ok', data: { updated: 1, created: 0 } });
    await tick();

    expect(signalOf<boolean>('actOpen')()).toBe(false);
    await flushList('game'); // 提交成功后重新拉列表
    await post;
    expect(call<string>('actError')).toBe('');
  });

  it('币种文本解不出数组：本地拦下，一个请求都不发（后端 validator 只会回英文原文）', async () => {
    page.ngOnInit();
    await flushList('game');
    await openCurrency();

    // 三种都拦：解出来是对象 / 语法错 / 空串
    for (const bad of ['{"a":1}', 'not json', '   ']) {
      await call<Promise<void>>('submitAct', { currencies: bad });
      expect(signalOf<boolean>('actOpen')()).toBe(true); // 框不关，改完可重试
      const msg = call<string>('actError');
      expect(msg).toContain(DICT['game.currency_act']![1]); // 「游戏币种 需要是一个 JSON 数组」
      expect(msg).toBe(
        DICT['app.field_must_be_json_array']![1].replace('{name}', DICT['game.currency_act']![1]),
      );
    }
  });

  it('分配游戏：POST /game/category/assign，game_ids 按行切、trim、丢空行', async () => {
    page.ngOnInit();
    await flushList('game');
    call('pick', 'category');
    await flushList('category');

    const assign = call<Promise<void>>('run', { id: 'C1', name: '分类A' }, 'assign');
    await tick(); // 分配没有预取，直接开空框；这一跳是 run() 末尾的列表刷新
    expect(signalOf<boolean>('actOpen')()).toBe(true);
    expect(call<string>('actTitle')).toBe('game_category.assign_title');
    expect(call<{ currencies?: string } | null>('actValue')).toBeNull(); // 空白开局：分类侧拿不到当前关联
    await flushList('category');
    await assign;

    const post = call<Promise<void>>('submitAct', { game_ids: ' G1 \n\n  G2\n' });
    const req = http.expectOne((r) => r.url === '/admin/v1/game/category/assign');
    expect(req.request.method).toBe('POST');
    expect(req.request.body).toEqual({ category_id: 'C1', game_ids: ['G1', 'G2'] });
    req.flush({ code: 0, message: 'ok', data: { count: 2 } });
    await tick();
    await flushList('category');
    await post;
    expect(signalOf<boolean>('actOpen')()).toBe(false);
  });

  it('提交被服务端打回：消息留框里、框不关（改完可重试）', async () => {
    page.ngOnInit();
    await flushList('game');
    call('pick', 'category');
    await flushList('category');

    const assign = call<Promise<void>>('run', { id: 'C1', name: '分类A' }, 'assign');
    await tick();
    await flushList('category');
    await assign;

    const post = call<Promise<void>>('submitAct', { game_ids: 'NOPE' });
    http
      .expectOne((r) => r.url === '/admin/v1/game/category/assign')
      .flush(
        { code: 422, message: 'The selected game_ids.0 is invalid.' },
        { status: 422, statusText: 'Unprocessable Entity' },
      );
    await post;

    expect(signalOf<boolean>('actOpen')()).toBe(true);
    expect(call<string>('actError')).toContain('game_ids.0 is invalid');
    expect(call<string>('error')).toBe(''); // 服务端回执进框，不污染列表级提示
  });

  it('两个动作弹框的字段/提示引用的词条键都在词典里（拼错的键会原样显示成 game.xxx）', async () => {
    page.ngOnInit();
    await flushList('game');
    await openCurrency();
    const used = [
      ...call<{ key: string; label: string }[]>('actions').map((a) => a.label),
      ...p<{ key: string; label: string }[]>('tabs').map((t) => t.label),
      ...[...signalOf<Field[]>('actFields')(), ...call<{ fields: Field[] }>('crud')!.fields].flatMap((f) =>
        [f.label, f.hint, f.placeholder].filter((k): k is string => !!k),
      ),
      call<string>('actTitle'),
    ];
    expect(used.filter((k) => !(k in DICT))).toEqual([]);
    expect(DICT['game.currency_act']?.[1]).toBe('游戏币种');
    expect(DICT['game_category.assign_games']?.[0]).toBe('Assign Games');
  });
});
