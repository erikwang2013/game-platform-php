/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { Tournament, TournamentDetail, TournamentStatus } from '../core/api.service';
import { TournamentsPage } from './tournaments';

type Sig<T> = { (): T; set(v: T): void };
type Probe = {
  status: Sig<TournamentStatus>;
  items: Sig<Tournament[]>;
  loading: Sig<boolean>;
  more: Sig<boolean>;
  error: Sig<string>;
  page: Sig<number>;
  lastPage: Sig<number>;
  cur: Sig<TournamentDetail | null>;
  note: Sig<string>;
  joinErr: Sig<string>;
  joining: Sig<boolean>;
  pick(s: TournamentStatus): void;
  loadMore(): void;
  emptyTitle(): string;
  feeText(t: Tournament): string;
  playerText(t: Tournament): string;
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
    expect(probe().feeText(t({ entry_fee: '0.0000' }))).toBe('免费');
    expect(probe().feeText(t({ entry_fee: '12.5000' }))).toBe('12.50');
    expect(probe().playerText(t({ max_players: 0 }))).toBe('3 人');
    expect(probe().playerText(t({ max_players: 16 }))).toBe('3 / 16 人');
  });

  it('报名费的「免费」判定走字符串（不再 Number()）：只认真正的零', () => {
    // 原先 feeText 写的是 `Number(t.entry_fee) > 0` —— 金额列过数值转型，本批明令禁止，
    // 已改为 moneyIsZero（与 money() 同一套字符串判据）。下面把「零的各种写法」与
    // 「看着像零但不是零」两侧都钉住，免得换判据时静默改了取值分支。
    init();
    for (const zero of ['0', '0.00', '0.00000000', '-0.00000000']) {
      expect(probe().feeText(t({ entry_fee: zero }))).toBe('免费');
    }
    // scale-8 最小非零量：过窄的判据会把它当零 ⇒ 免费赛事白送，这里必须仍是金额
    expect(probe().feeText(t({ entry_fee: '0.00000001' }))).toBe('0.00000001');
    expect(probe().feeText(t({ entry_fee: '12345678901234567890.12' }))).toBe(
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

    expect(probe().note()).toBe('报名成功，开赛后会出现在排行榜里。');
    expect(probe().cur()?.my_entry?.id).toBe('E1');
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
    expect(probe().emptyTitle()).toBe('暂无即将开始的赛事');
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
  });
});
