/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { Tournament, TournamentDetail, TournamentStatus } from '../core/api.service';
// 本文件下面已经有个 fixture 构造器叫 `t`（`Partial<Tournament> → Tournament`），
// 所以 i18n 的 `t` 改名导入，不改那个 fixture 的名字（它在本文件里被用了上百次）。
import { Msg, t as tr } from '../core/i18n/i18n';
import { TournamentsPage } from './tournaments';

type Sig<T> = { (): T; set(v: T): void };
type Probe = {
  status: Sig<TournamentStatus>;
  items: Sig<Tournament[]>;
  loading: Sig<boolean>;
  more: Sig<boolean>;
  error: Sig<Msg>;
  page: Sig<number>;
  lastPage: Sig<number>;
  cur: Sig<TournamentDetail | null>;
  dLoading: Sig<boolean>;
  dErr: Sig<Msg>;
  note: Sig<Msg>;
  joinErr: Sig<Msg>;
  joining: Sig<boolean>;
  pick(s: TournamentStatus): void;
  loadMore(): void;
  emptyTitle(): string;
  feeText(t: Tournament): Msg;
  playerText(t: Tournament): Msg;
  canJoin(t: Tournament): boolean;
  open(id: string): void;
  openFresh(id: string): void;
  join(t: TournamentDetail): void;
  close(): void;
};

const FUTURE = '2099-01-01 00:00:00';
const PAST = '2020-01-01 00:00:00';

const t = (o: Partial<Tournament> = {}): Tournament => ({
  id: 'T1',
  name: '周末杯',
  slug: 'weekend-cup',
  type: 'solo',
  description: null,
  game: { id: 'G1', name: '德州扑克' },
  prize_pool: '1000.0000',
  entry_fee: '0.0000',
  player_count: 3,
  max_players: 16,
  start_at: FUTURE,
  end_at: '2099-01-02 00:00:00',
  ...o,
});

const d = (o: Partial<TournamentDetail> = {}): TournamentDetail => ({
  ...t(),
  my_entry: null,
  leaderboard: [],
  ...o,
});

/**
 * 赛事页的契约：
 *  ① 默认落在 **upcoming**（服务端默认是 active，而 active 的定义就是 start_at <= now
 *     ⇒ 那一栏一条都报不进，见 TournamentController::join）。
 *  ② 报名按钮**只在开赛前**摆：本地预判是为了不摆一个必然 400 的按钮；真闸在服务端。
 *  ③ 报名成功后**服务端回读** my_entry、并且列表重拉（人数变了），不本地推算。
 *  ④ 报名失败把服务端 message 原样透出（已报名/已开赛/满员三种都不是"操作失败"）。
 */
describe('TournamentsPage 赛事', () => {
  let http: HttpTestingController;
  let page: TournamentsPage;

  const probe = (): Probe => page as unknown as Probe;

  /**
   * `Msg` 是**两态**（词条键 / 服务端原文）：只有键那一态的读点需要解析，原文那一态原样透出。
   * **故意不统一**：把原文那两处也裹进来，就再也证明不了「服务端 message 被原样透出」这一支。
   * 同一形状见 `me.spec.ts` / `activities.spec.ts` / `me-export.spec.ts` / `login.spec.ts`。
   *
   * 本文件里裹 `txt()` 的读点：`feeText` / `playerText`（本批改成回 `Msg` 了 —— 「免费」与
   * 「N / M 人」是**键**、金额与原文是**串**）。
   * 不裹的读点：`error()` / `joinErr()` —— 那两处断言的正是**服务端原文原样透出**。
   * 另有 `emptyTitle()` / `TABS` 回的是裸**键名**，`txt()` 会把它当原文原样吐出 ⇒ 那两处走 `tr()`。
   */
  const txt = (v: Msg): string => (typeof v === 'string' ? v : tr(v.key, v.params));

  const list = (items: Tournament[], lastPage = 1) => ({
    code: 0,
    message: 'ok',
    data: { items, total: items.length, page: 1, last_page: lastPage },
  });

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting(), provideRouter([])],
    });
    http = TestBed.inject(HttpTestingController);
    page = TestBed.runInInjectionContext(() => new TournamentsPage());
  });

  afterEach(() => {
    try {
      http.verify();
    } finally {
      TestBed.resetTestingModule();
    }
  });

  const init = (items: Tournament[] = [t()]): void => {
    http.expectOne((r) => r.url === '/api/v1/tournament/list').flush(list(items));
  };

  it('首屏打的是 status=upcoming（服务端默认 active 那一栏一条都报不进）', () => {
    const req = http.expectOne((r) => r.url === '/api/v1/tournament/list');
    expect(req.request.params.get('status')).toBe('upcoming');
    expect(req.request.params.get('page')).toBe('1');
    req.flush(list([t()]));

    expect(probe().items().length).toBe(1);
    expect(probe().loading()).toBe(false);
  });

  it('切标签：按新 status 重打，页码回到第 1 页（列表整体替换而不是追加）', () => {
    init([t({ id: 'T1', name: '第一' })]);
    probe().pick('ended');
    const req = http.expectOne((r) => r.url === '/api/v1/tournament/list');
    expect(req.request.params.get('status')).toBe('ended');
    req.flush(list([t({ id: 'T9', name: '已结束的' })]));

    expect(probe().status()).toBe('ended');
    expect(probe().items().map((x) => x.id)).toEqual(['T9']);
  });

  it('同一标签重复点不发请求（幂等）', () => {
    init();
    probe().pick('upcoming');
    http.expectNone(() => true);
  });

  it('加载更多：page+1 且**追加**而不是替换', () => {
    http
      .expectOne((r) => r.url === '/api/v1/tournament/list')
      .flush({ code: 0, message: 'ok', data: { items: [t({ id: 'T1' })], total: 21, page: 1, last_page: 2 } });

    probe().loadMore();
    const req = http.expectOne((r) => r.url === '/api/v1/tournament/list');
    expect(req.request.params.get('page')).toBe('2');
    req.flush({ code: 0, message: 'ok', data: { items: [t({ id: 'T2' })], total: 21, page: 2, last_page: 2 } });

    expect(probe().items().map((x) => x.id)).toEqual(['T1', 'T2']);
    expect(probe().more()).toBe(false);
  });

  it('报名的本地预判只看「开赛了没有」：未开赛可报、已开赛不可报', () => {
    init();
    expect(probe().canJoin(t({ start_at: FUTURE }))).toBe(true);
    expect(probe().canJoin(t({ start_at: PAST }))).toBe(false);
    // 时间戳读不出来（服务端给了空串）时**不摆**按钮 —— 摆一个大概率 400 的按钮不如不摆
    expect(probe().canJoin(t({ start_at: '' }))).toBe(false);
  });

  it('报名费 0 显示「免费」；人数上限 0 是「不限量」而不是「3 / 0 人」', () => {
    init();
    expect(txt(probe().feeText(t({ entry_fee: '0.0000' })))).toBe('免费');
    expect(txt(probe().feeText(t({ entry_fee: '12.5000' })))).toBe('12.50');
    expect(txt(probe().playerText(t({ max_players: 0 })))).toBe('3 人');
    expect(txt(probe().playerText(t({ max_players: 16 })))).toBe('3 / 16 人');
    // 两态的分界：键那一支必须是**对象**（渲染期才查表），串那一支必须是 **raw 串**
    // （`money()` 的成品，别再退回成键 —— 下游要的是成文）
    expect(typeof probe().feeText(t({ entry_fee: '0.0000' }))).toBe('object');
    expect(typeof probe().feeText(t({ entry_fee: '12.5000' }))).toBe('string');
  });

  it('报名费的「免费」判定走字符串（不再 Number()）：只认真正的零', () => {
    // 原先 feeText 写的是 `Number(t.entry_fee) > 0` —— 金额列过数值转型，本批明令禁止，
    // 已改为 moneyIsZero（与 money() 同一套字符串判据）。下面把「零的各种写法」与
    // 「看着像零但不是零」两侧都钉住，免得换判据时静默改了取值分支。
    init();
    for (const zero of ['0', '0.00', '0.00000000', '-0.00000000']) {
      expect(txt(probe().feeText(t({ entry_fee: zero })))).toBe('免费');
    }
    // scale-8 最小非零量：过窄的判据会把它当零 ⇒ 免费赛事白送，这里必须仍是金额
    expect(txt(probe().feeText(t({ entry_fee: '0.00000001' })))).toBe('0.00000001');
    expect(txt(probe().feeText(t({ entry_fee: '12345678901234567890.12' })))).toBe(
      '12,345,678,901,234,567,890.12',
    );
  });

  it('报名成功：POST → 重拉详情（my_entry 以服务端为准）→ 重拉列表，且提示**不被重拉擦掉**', () => {
    init();
    probe().open('T1');
    http.expectOne('/api/v1/tournament/T1').flush({ code: 0, message: 'ok', data: d() });

    probe().join(probe().cur()!);
    const joinReq = http.expectOne('/api/v1/tournament/T1/join');
    expect(joinReq.request.method).toBe('POST');
    joinReq.flush({ code: 0, message: 'Entry confirmed', data: { id: 'E1', score: '0', rank: 0 } });

    // 成功后两件事：详情重拉 + 列表重拉
    http
      .expectOne('/api/v1/tournament/T1')
      .flush({ code: 0, message: 'ok', data: d({ my_entry: { id: 'E1', score: '0', rank: 0 }, player_count: 4 }) });
    http.expectOne((r) => r.url === '/api/v1/tournament/list').flush(list([t({ player_count: 4 })]));

    expect(txt(probe().note())).toBe('报名成功，开赛后会出现在排行榜里。');
    expect(probe().cur()?.my_entry?.id).toBe('E1');
    expect(probe().items()[0]!.player_count).toBe(4);
  });

  /**
   * 报名那条 POST 的竞态（**能红**：删掉 `join()` 里那两行 `if (this.loadingId !== t.id) return;`）。
   *
   * 与 `open()` 的详情请求是**两条独立的在途请求**，上面那两条用例盖不到这条路。
   * 现场：点「报名参赛」→ 请求在路上 → 用户点「关闭」→ 回包落地 ⇒ 原写法 `this.open(t.id)`
   * 把框重新开出来，还带一句「报名成功」。
   */
  it('报名回包晚到：报名途中关框，不许把框重新弹开（但列表照刷、joining 照复位）', () => {
    init();
    probe().open('T1');
    http.expectOne('/api/v1/tournament/T1').flush({ code: 0, message: 'ok', data: d() });

    probe().join(probe().cur()!);
    const joinReq = http.expectOne('/api/v1/tournament/T1/join');

    probe().close(); // ← 报名请求还在路上，用户点了「关闭」
    joinReq.flush({ code: 0, message: 'Entry confirmed', data: { id: 'E1', score: '0', rank: 0 } });

    // 列表照刷（人数变了）；但详情**不得**再回读，框也不许回来
    http.expectOne((r) => r.url === '/api/v1/tournament/list').flush(list([t({ player_count: 4 })]));
    http.expectNone('/api/v1/tournament/T1');

    expect(probe().cur()).toBeNull(); // 不挂判据时这里被 open() 重新填上 ⇒ 框弹回来
    expect(probe().dLoading()).toBe(false);
    expect(probe().note()).toBe('');
    // 复位不能跟着判据一起被跳过：否则下次开框的按钮永久停在「报名中…」
    expect(probe().joining()).toBe(false);
    expect(probe().items()[0]!.player_count).toBe(4);
  });

  it('报名失败：服务端的 message 原样透出，不吞成「操作失败」', () => {
    init();
    probe().open('T1');
    http.expectOne('/api/v1/tournament/T1').flush({ code: 0, message: 'ok', data: d() });

    probe().join(probe().cur()!);
    http
      .expectOne('/api/v1/tournament/T1/join')
      .flush({ code: 422, message: 'Already entered', data: [] });
    // 不退化成"报名成功"：失败时不该重拉、也不该写成功提示
    http.expectNone('/api/v1/tournament/T1');

    expect(probe().joinErr()).toBe('Already entered');
    expect(probe().note()).toBe('');
  });

  it('列表返回 503（FeatureFlag 关掉）：把服务端文案原样透出', () => {
    http
      .expectOne((r) => r.url === '/api/v1/tournament/list')
      .flush({ code: 503, message: 'Tournaments not available', data: [] });

    expect(probe().error()).toBe('Tournaments not available');
    expect(probe().items()).toEqual([]);
    expect(probe().loading()).toBe(false);
  });

  it('空态文案按标签区分', () => {
    init([]);
    // emptyTitle() 回的是**裸键名**（模板上 `{{ emptyTitle() | t }}`）⇒ 过 tr() 查表；
    // 用 txt() 会把它当原文原样吐出来（`txt` 对字符串是恒等），断言就恒绿了。
    for (const [s, word] of [
      ['upcoming', '暂无即将开始的赛事'],
      ['active', '当前没有进行中的赛事'],
      ['ended', '还没有已结束的赛事'],
    ] as const) {
      probe().status.set(s);
      expect(tr(probe().emptyTitle())).toBe(word);
    }
  });

  it('DOM：已报名的赛事显示「已报名」且**不摆**报名按钮（哪怕还没开赛）', () => {
    TestBed.resetTestingModule();
    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting(), provideRouter([])],
    });
    http = TestBed.inject(HttpTestingController);
    const fixture = TestBed.createComponent(TournamentsPage);
    fixture.detectChanges();
    http.expectOne((r) => r.url === '/api/v1/tournament/list').flush(list([t()]));
    fixture.detectChanges();

    const el: HTMLElement = fixture.nativeElement;
    expect(el.textContent).toContain('周末杯');
    expect(el.textContent).toContain('免费');

    // 打开详情（未开赛 + 没报名 ⇒ 有按钮）
    (el.querySelector('.row.asbtn') as HTMLButtonElement).click();
    fixture.detectChanges();
    http.expectOne('/api/v1/tournament/T1').flush({ code: 0, message: 'ok', data: d() });
    fixture.detectChanges();
    const btn = () => [...el.querySelectorAll('button')].find((b) => b.textContent?.includes('报名参赛'));
    expect(btn()).toBeTruthy();

    // 关掉再开一条已报名的 —— 报名按钮必须消失
    (el.querySelector('.modal header button') as HTMLButtonElement).click();
    fixture.detectChanges();
    (el.querySelector('.row.asbtn') as HTMLButtonElement).click();
    fixture.detectChanges();
    http
      .expectOne('/api/v1/tournament/T1')
      .flush({ code: 0, message: 'ok', data: d({ my_entry: { id: 'E1', score: '7', rank: 2 } }) });
    fixture.detectChanges();

    expect(btn()).toBeFalsy();
    expect(el.textContent).toContain('已报名');
    expect(el.textContent).toContain('第 2 名');
    // 漏 `| t` / `| mt` 的两个症状一次钉住：字符串键名被原样印出来、`Msg` 对象被插值成
    // `[object Object]`（本批 `feeText`/`playerText` 改成回对象后，就靠这条兜底）。
    expect(el.textContent).not.toMatch(/\b(?:tourney|common|wallet|mygames)\./);
    expect(el.textContent).not.toContain('[object Object]');

    // ④ 的**接线**钉子（指令自己的用例只证明指令本身能用，证明不了它真被挂上去）：
    // Esc 走 `uiModal` 的 dismiss → `(dismiss)="close()"`。把模板上那两个属性任一去掉即变红。
    (el.querySelector('.modal') as HTMLElement).dispatchEvent(
      new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true }),
    );
    fixture.detectChanges();
    expect(el.querySelector('.modal')).toBeNull();

    // `@else`（已开赛 + 没报名）那一支 —— 此前零覆盖。文案过表，且本批把旧文
    // 「报名已截止（开赛后不能再报名）」对齐成了 react 树同键的原句（真闸在服务端，见文件头注释）。
    (el.querySelector('.row.asbtn') as HTMLButtonElement).click();
    fixture.detectChanges();
    http
      .expectOne('/api/v1/tournament/T1')
      .flush({ code: 0, message: 'ok', data: d({ start_at: PAST }) });
    fixture.detectChanges();
    expect(el.textContent).toContain('报名已截止（服务端在开赛后拒收报名）');
  });

  /**
   * 慢网下的竞态（本条与下一条都**能红**：把 `open()` 里那两行
   * `if (this.loadingId !== id) return;` 删掉，两条立刻变红）。
   *
   * 现场：点开一行 → 框里显示「加载中…」→ 用户等不及，点遮罩/关闭 → 回包这才落地。
   * 不挂判据时 `cur.set(d)` 照写，**用户刚关掉的那个框自己又弹回来**，只能再关一次。
   */
  it('关框后落地的详情回包不再把框弹回来（在途请求按代号作废）', () => {
    init();
    probe().open('T1');
    probe().close(); // 用户没等回包就关了
    http.expectOne('/api/v1/tournament/T1').flush({ code: 0, message: 'ok', data: d() });

    expect(probe().cur()).toBeNull(); // 不挂判据时这里是 d() ⇒ 框自己弹回来
    expect(probe().dErr()).toBe(''); // 失败回包同理，不能把「加载失败」写进已关掉的框
    expect(probe().dLoading()).toBe(false);
  });

  it('换行：前一条的回包晚到也不能盖掉当前这条（代号随 open 改写）', () => {
    init();
    probe().open('T1');
    probe().open('T2'); // 取消 T1 的框 ⇒ 只有 T2 的回包算数
    http
      .expectOne('/api/v1/tournament/T1')
      .flush({ code: 0, message: 'ok', data: d({ id: 'T1', name: '旧的' }) });
    expect(probe().cur()).toBeNull(); // T1 的回包已作废

    http
      .expectOne('/api/v1/tournament/T2')
      .flush({ code: 0, message: 'ok', data: d({ id: 'T2', name: '新的' }) });
    expect(probe().cur()?.id).toBe('T2');
    expect(probe().cur()?.name).toBe('新的');
  });
});
