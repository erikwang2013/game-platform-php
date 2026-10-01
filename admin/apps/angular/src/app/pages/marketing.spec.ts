/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { Row } from '../core/api.service';
import { Crud, Field } from '../core/crud';
import { use } from '../core/i18n/i18n';
import { Marketing } from './marketing';

/**
 * 「营销中心」（marketing.ts：优惠券 / VIP 等级）的渲染级钉子。
 *
 * 这里的断言**一律读渲染出来的 DOM**，不读源码也不读常量数组 —— 词条抽出去之后，
 * 「模板上挂的是键、渲染时查表、换语言要重画」这三件事各自都有独立的坏法，
 * 而三种坏法在源码里都长得像对的：
 *  1. 键没挂上 `| t` ⇒ 界面上直接印出 `coupon.title` 这样的裸键；
 *  2. `t` 管道被改成 pure ⇒ 组件不重建就永远停在首次的语言（切语言整页不动）；
 *  3. 拼接类文案（这里是 game_id 的「加载失败」后缀）里，被拼的那个字段本身也是**键**，
 *     不先查表就拼出来 = 前缀露键名。
 * 第 3 条尤其隐蔽：`COUPON_FIELDS` 里 `label: 'coupon.game_id'` 是完全正确的一行，
 * 错的是 `couponFields()` 里拼后缀时把它当成了成品文案。
 */
describe('Marketing 营销中心', () => {
  let http: HttpTestingController;

  /** 页面方法/字段多是 protected，测试侧按鸭子类型取用 */
  type M = {
    crud(): Crud | null;
    fields(): Field[];
  };

  const ROW: Row = { id: 'C1', name: '满减券', status: 1, game_id: 0, used_qty: 0 };

  beforeEach(() => {
    use('zh');
    TestBed.configureTestingModule({ providers: [provideHttpClient(), provideHttpClientTesting()] });
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => {
    localStorage.clear();
    try {
      http.verify();
    } finally {
      TestBed.resetTestingModule();
    }
  });

  const tick = (): Promise<void> => new Promise((resolve) => setTimeout(resolve, 0));
  const url = (r: { url: string }): string => r.url.split('?')[0]!;

  /** 首屏取数：券列表与游戏表（游戏表是 game_id 下拉的选项源）同批发出，两个 GET 都要应答 */
  const loadList = async (f: ComponentFixture<Marketing>, rows: Row[], gamesOk = true): Promise<void> => {
    const game = http.expectOne((r) => r.method === 'GET' && url(r) === '/admin/v1/game/list');
    if (gamesOk) game.flush({ code: 0, message: 'ok', data: { list: [{ id: 'G1', name: '斗地主' }] } });
    else game.flush({ code: 500, message: 'games down' }, { status: 500, statusText: 'Server Error' });
    http
      .expectOne((r) => r.method === 'GET' && url(r) === '/admin/v1/coupon/list')
      .flush({ code: 0, message: 'ok', data: { list: rows, total: rows.length } });
    await tick();
    f.detectChanges();
  };

  const el = (f: ComponentFixture<Marketing>): HTMLElement => f.nativeElement as HTMLElement;
  const texts = (f: ComponentFixture<Marketing>, sel: string): string[] =>
    [...el(f).querySelectorAll(sel)].map((n) => n.textContent!.trim());
  const duck = (f: ComponentFixture<Marketing>): M =>
    f.componentInstance as unknown as M;

  it('标签页/筛选下拉逐字查表，且**换语言只重渲染就整页变**（pure 管道会在这里变红）', async () => {
    const f = TestBed.createComponent(Marketing);
    f.detectChanges();
    await loadList(f, [ROW]);

    expect(texts(f, '.tabs button')).toEqual(['优惠券', 'VIP 等级']);
    // 筛选下拉的 label 也是词条，值保持 ''/'1'/'0' 不变（后端按它过滤）
    expect(texts(f, 'select.input option')).toEqual(['全部状态', '启用', '停用']);

    use('en');
    f.detectChanges(); // 只重渲染，不重建组件：pure:false 是这里唯一能让文案变的原因
    expect(texts(f, '.tabs button')).toEqual(['Coupons', 'VIP Level Management']);
    expect(texts(f, 'select.input option')).toEqual(['All statuses', 'Enabled', 'Disabled']);
    expect(el(f).textContent).toContain('Marketing');
  });

  it('抽屉标题是**值**不是键（ui-drawer 的 title() 不过 t 管道）⇒ DOM 里必须是译文', async () => {
    const f = TestBed.createComponent(Marketing);
    f.detectChanges();
    await loadList(f, [ROW]);

    el(f).querySelector('tbody tr')!.dispatchEvent(new MouseEvent('click'));
    await tick();
    // 行点击顺带拉统计；空对象 ⇒ 落到「暂无」分支（同一段模板里还有两处词条）
    http
      .expectOne((r) => r.method === 'GET' && url(r) === '/admin/v1/coupon/C1/stats')
      .flush({ code: 0, message: 'ok', data: {} });
    await tick();
    f.detectChanges();

    const title = (): string => texts(f, '.drawer header b').join('');
    expect(title()).toBe('优惠券统计');
    expect(el(f).textContent).not.toContain('coupon.stats_title');
    expect(texts(f, '.kv dt')).toEqual(['提示']);
    expect(texts(f, '.kv dd')).toEqual(['该券暂无可展示统计']);

    use('en');
    f.detectChanges();
    expect(title()).toBe('Coupon stats');
    expect(texts(f, '.kv dt')).toEqual(['Note']);
    expect(texts(f, '.kv dd')).toEqual(['No stats to show for this coupon']);
  });

  it('游戏表挂掉：game_id 的 label 先查表再拼后缀，不把键名当地址露出来', async () => {
    const f = TestBed.createComponent(Marketing);
    f.detectChanges();
    await loadList(f, [], false); // 游戏表 500 ⇒ gamesErr 有值，券页本身不打错误态

    const fields = duck(f).crud()!.fields;
    const gid = fields.find((x) => x.name === 'game_id')!;
    // 前缀是**译出来的**字段名（'适用游戏'），不是 'coupon.game_id'；
    // 后缀来自 coupon.games_failed，键没登记时 t() 会原样回键名 ⇒ 一并钉住
    expect(gid.label).toBe('适用游戏 —— 游戏列表加载失败：games down');
    expect(gid.label).not.toContain('coupon.');
    // 拼后缀只动 game_id 一个：兄弟字段仍是**键**（form-modal 渲染时才查表）——
    // 这条同时说明上面的「已译」不是碰巧，是这一个字段被单独换过了
    expect(fields.find((x) => x.name === 'name')!.label).toBe('coupon.name');
  });

  it('删除确认的对象标识：级联语义（连领取记录一起删）随语言走', async () => {
    const f = TestBed.createComponent(Marketing);
    f.detectChanges();
    await loadList(f, [ROW]);

    const label = (): string => duck(f).crud()!.label!(ROW);
    expect(label()).toBe('满减券｜连同全部用户领取记录');

    use('en');
    expect(label()).toBe('满减券 | along with every user claim record');

    // 没有 name 的行回落 hashid（idOf），不能拼出 undefined
    expect(duck(f).crud()!.label!({ id: 'C9' })).toBe('C9 | along with every user claim record');
  });
});
