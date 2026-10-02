/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { Activity, ActivityProgress, ActivityReward } from '../core/api.service';
import { Msg, t } from '../core/i18n/i18n';
import { ActivitiesPage } from './activities';

type Sig<T> = { (): T; set(v: T): void };
type Row = { a: Activity; p: ActivityProgress };
type Probe = {
  load(): void;
  checkin(a: Activity): void;
  rows: () => Row[];
  loading: Sig<boolean>;
  err: Sig<string>;
  note: Sig<Msg>;
  rowErr: Sig<string>;
  reward: Sig<ActivityReward[] | null>;
  busyId: Sig<string | null>;
  pct(p: ActivityProgress): number;
  progressText(p: ActivityProgress): string;
  statusLabel(p: ActivityProgress): string;
  actionLabel(r: Row): string;
  rewardText(l: ActivityReward[]): string;
  gameLink(a: Activity): string;
  blockReason(a: Activity): string;
};

const A1: Activity = { id: 'A1', type: 'signin', name: '每日签到', status: 1, game_id: 0 };
const A2: Activity = { id: 'A2', type: 'daily_task', name: '每日任务', status: 1, game_id: 'G9' };
const A3: Activity = { id: 'A3', type: 'invite', name: '邀请活动', status: 1, game_id: 0 };
const A4: Activity = { id: 'A4', type: 'signin', name: '某游戏签到', status: 1, game_id: 'G9' };

const p = (o: Partial<ActivityProgress>): ActivityProgress => ({
  activity_id: 'A1',
  current: 0,
  target: 0,
  status: 'progressing',
  ...o,
});

/**
 * 运营活动页：合并两个接口 + 签到三种终态。
 *
 * 钉的重点是**奖励是真钱**这件事：服务端在同一事务里写 reward_log + 钱包，
 * 前端只负责如实展示并重拉进度，**不许自己累加余额**。所以这里既钉「rewarded 会显示明细」，
 * 也钉「already/progressing 不显示任何奖励」。
 */
describe('ActivitiesPage 运营活动', () => {
  let http: HttpTestingController;
  let page: ActivitiesPage;

  const probe = (): Probe => page as unknown as Probe;

  /**
   * `Msg` 是**两态**（词条键 / 服务端原文）：只有键那一态的读点需要解析，原文那一态原样透出。
   * **故意不统一**：把 `rowErr` 那两处也裹进来，就再也证明不了「服务端 message 被原样透出」这一支。
   */
  const txt = (v: Msg): string => (typeof v === 'string' ? v : t(v.key, v.params));

  /** 冲掉构造函数里 forkJoin 发的两个请求 */
  const flushInit = (list: Activity[], prog: ActivityProgress[]): void => {
    http.expectOne('/api/v1/activities/list').flush({ code: 0, message: 'ok', data: { list } });
    http
      .expectOne('/api/v1/activities/progress')
      .flush({ code: 0, message: 'ok', data: { list: prog } });
  };

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        provideRouter([{ path: '', children: [] }]),
      ],
    });
    http = TestBed.inject(HttpTestingController);
    page = TestBed.runInInjectionContext(() => new ActivitiesPage());
  });

  afterEach(() => {
    try {
      http.verify();
    } finally {
      TestBed.resetTestingModule();
    }
  });

  it('只发 list + progress 两个请求就渲染（不逐条拉 detail，避免 N+1）', () => {
    flushInit([A1, A2], [p({ activity_id: 'A1', current: 1, target: 1, status: 'rewarded' })]);
    // 再来一次也只会是这两条路径
    http.expectNone('/api/v1/activities/A1');
    http.expectNone('/api/v1/activities/A1/checkin');
    expect(probe().rows().length).toBe(2);
    expect(probe().loading()).toBe(false);
  });

  it('没参与过的活动补零值：显示「未开始」而不是 undefined', () => {
    flushInit([A1, A2], [p({ activity_id: 'A1', current: 1, target: 2 })]);
    const [r1, r2] = probe().rows();
    expect(r1.p.current).toBe(1);
    expect(r2.p.target).toBe(0);
    expect(probe().statusLabel(r2.p)).toBe('未开始');
    expect(probe().progressText(r2.p)).toBe('今日还没开始');
    expect(probe().pct(r2.p)).toBe(0);
    expect(probe().actionLabel(r1)).toBe('签到');
    // daily_task 现在**不给按钮**（点了会白拿奖 + 锁死当天进度），改摆原因 ⇒ 不再断言「领取」这个文案
    expect(probe().blockReason(A2)).toBe('按任务条件自动累计');
  });

  /**
   * 签到按钮的**闸**：只有 `game_id=0` 的 signin 才摆，其余三类一律摆原因。
   * 三类各有各的判据（见 activities.ts 文件头），但共同点是**都不该有按钮** ——
   * daily_task 是「点了白拿奖 + 锁死当天进度」，另两类是「点了必然 400」。
   */
  it('blockReason：只放行 game_id=0 的 signin，另三类各给各的原因', () => {
    flushInit([], []);
    expect(probe().blockReason(A1)).toBe(''); // 全平台签到 —— 唯一可签的
    expect(probe().blockReason(A2)).toBe('按任务条件自动累计');
    expect(probe().blockReason(A3)).toBe('按好友注册自动累计');
    expect(probe().blockReason(A4)).toBe('请在对应游戏内完成');
  });

  it('达标发奖：显示奖励明细并重拉进度', () => {
    flushInit([A1], [p({ current: 1, target: 3 })]);
    probe().checkin(A1);
    http.expectOne('/api/v1/activities/A1/checkin').flush({
      code: 0,
      message: 'ok',
      data: { status: 'rewarded', reward: [{ type: 'platform_coin', amount: '1.5' }] },
    });
    expect(probe().rewardText(probe().reward()!)).toBe('平台币 1.50');
    // 签完必须重拉进度（余额与进度都变了），不能靠本地累加
    http
      .expectOne('/api/v1/activities/progress')
      .flush({ code: 0, message: 'ok', data: { list: [p({ current: 3, target: 3, status: 'rewarded' })] } });
    expect(probe().rows()[0].p.status).toBe('rewarded');
    expect(probe().statusLabel(probe().rows()[0].p)).toBe('已领取');
    expect(probe().actionLabel(probe().rows()[0])).toBe('今日已领');
  });

  it('今天已领过（already）：给文案但**不**显示奖励明细', () => {
    flushInit([A1], [p({ current: 3, target: 3, status: 'rewarded' })]);
    probe().checkin(A1);
    http
      .expectOne('/api/v1/activities/A1/checkin')
      .flush({ code: 0, message: 'ok', data: { status: 'already' } });
    expect(txt(probe().note())).toContain('已经领过');
    expect(probe().reward()).toBeNull();
    http.expectOne('/api/v1/activities/progress').flush({ code: 0, message: 'ok', data: { list: [] } });
  });

  it('未达标（progressing）：只记一次，不发奖', () => {
    flushInit([A1], [p({ current: 1, target: 3 })]);
    probe().checkin(A1);
    http
      .expectOne('/api/v1/activities/A1/checkin')
      .flush({ code: 0, message: 'ok', data: { status: 'progressing' } });
    expect(probe().reward()).toBeNull();
    expect(txt(probe().note())).toContain('还没达到目标');
    http.expectOne('/api/v1/activities/progress').flush({ code: 0, message: 'ok', data: { list: [] } });
  });

  it('业务失败（code=400）把服务端 message 透出，且不重拉进度', () => {
    flushInit([A1], []);
    probe().checkin(A1);
    http
      .expectOne('/api/v1/activities/A1/checkin')
      .flush({ code: 400, message: 'Activity not available', data: [] });
    expect(probe().rowErr()).toBe('Activity not available');
    expect(probe().busyId()).toBeNull();
    expect(probe().reward()).toBeNull();
    http.expectNone('/api/v1/activities/progress');
  });

  it('进度条封顶 100%，目标为 0 时不显示 0/0', () => {
    flushInit([A1], [p({ current: 9, target: 3 })]);
    expect(probe().pct(probe().rows()[0].p)).toBe(100);
    expect(probe().progressText(probe().rows()[0].p)).toBe('今日进度 9 / 3');
    expect(probe().progressText(p({ target: 0 }))).toBe('今日还没开始');
  });

  it('game_id=0 是全平台，不给游戏链接；>0 才给', () => {
    flushInit([A1, A2], []);
    expect(probe().gameLink(A1)).toBe('');
    expect(probe().gameLink(A2)).toBe('G9');
  });

  it('没有可发放的奖励时也给一句实话，而不是空白', () => {
    flushInit([], []); // 构造函数那两个请求仍要冲掉，否则 afterEach 的 verify() 会报未决请求
    expect(probe().rewardText([])).toContain('没有可发放');
  });
});

/**
 * DOM 级：**按钮到底在不在**。
 *
 * 上面那组只钉 `blockReason()` 的返回值，钉不住「模板真的按它分流」——
 * 把模板改回无条件摆按钮，那组照样全绿。这条渲染出来数按钮。
 */
describe('ActivitiesPage 签到按钮的闸（DOM 级）', () => {
  let http: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        provideRouter([{ path: '', children: [] }]),
      ],
    });
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => {
    try {
      http.verify();
    } finally {
      TestBed.resetTestingModule();
    }
  });

  it('四类活动里**只有**全平台签到摆按钮；daily_task 一片按钮都不该有', () => {
    const fixture = TestBed.createComponent(ActivitiesPage);
    fixture.detectChanges();
    http
      .expectOne('/api/v1/activities/list')
      .flush({ code: 0, message: 'ok', data: { list: [A1, A2, A3, A4] } });
    http.expectOne('/api/v1/activities/progress').flush({ code: 0, message: 'ok', data: { list: [] } });
    fixture.detectChanges();

    const el: HTMLElement = fixture.nativeElement;
    const cards = [...el.querySelectorAll('.card.act')];
    expect(cards.length).toBe(4);
    const btnIn = (c: Element) => c.querySelector('button');

    expect(btnIn(cards[0]!), 'game_id=0 的 signin —— 唯一可签的').toBeTruthy();
    expect(btnIn(cards[0]!)!.textContent).toContain('签到');
    expect(btnIn(cards[1]!), 'daily_task —— 点了会白拿奖并锁死当天进度，绝不能有按钮').toBeFalsy();
    expect(btnIn(cards[2]!), 'invite —— checkin 的 ctx 里没有 activity_id，必 400').toBeFalsy();
    expect(btnIn(cards[3]!), '绑了游戏的 signin —— checkin 的 ctx.game_id 恒 0，必 400').toBeFalsy();

    // 不摆按钮不是留白：每一类都要说明为什么
    expect(el.textContent).toContain('按任务条件自动累计');
    expect(el.textContent).toContain('按好友注册自动累计');
    expect(el.textContent).toContain('请在对应游戏内完成');
  });
});
