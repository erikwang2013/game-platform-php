/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { use } from '../core/i18n/i18n';
import { RiskCharts } from './risk-charts';

/**
 * 风控总览的补块。三件事各自会静默坏掉：
 *  1. `total` 是**嵌套对象**而 `scalarsOf()` 只认标量 ⇒ 不单独接的话，全屏最该看到的
 *     「共命中多少 / 拦了多少」根本不渲染（点开原始响应才看得到）；
 *  2. 补块得跟着父组件刷新走（父组件每次重拉都换一个新对象）；
 *  3. 但它**只该认 raw 一个入参** —— Api 造请求头时会同步读语言信号
 *     （`api.service.ts` 的 X-Language）⇒ 不挡住，切一次语言就白拉两个端点。
 *     真机实测过：切 ja、切回 zh，各多打一对请求。
 */
describe('RiskCharts', () => {
  let http: HttpTestingController;
  let fixture: ComponentFixture<RiskCharts>;
  const tick = (): Promise<void> => new Promise((resolve) => setTimeout(resolve, 0));

  const OVERVIEW = {
    from: '2026-09-25 00:00:00',
    to: '2026-10-01 23:59:59',
    group_by: 'day',
    series: [
      { bucket: '2026-09-26', hits: 120, blocked: 30 },
      { bucket: '2026-09-29', hits: 260, blocked: 140 },
    ],
    total: { hits: 660, blocked: 270, warned: 220, logged: 170 },
    block_rate: 40.91,
  };

  /** /risk/dashboard 的近 24h 快照：前五个量复用旧词，后四个规模量是新词 */
  const DASH = {
    total_events_24h: 42,
    blocked_24h: 7,
    warned_24h: 5,
    logged_24h: 30,
    block_rate_24h: 16.67,
    enabled_rules: 3,
    total_rules: 8,
    blacklist_ips: 12,
    device_clusters: 4,
    recent_events: [
      { id: 'L1', user_id: 'UH1', type: 'frequency', action: 'block', detail: '60s 内 9 次', created_at: '2026-10-01 10:00:00' },
    ],
  };

  /** /risk/hit-trend：series 是**按规则类型分组的对象**（不是数组），值里只有一条命中过两天 */
  const TREND = {
    from: '2026-09-25 00:00:00',
    to: '2026-10-01 23:59:59',
    rule_type: '',
    series: {
      frequency: [
        { bucket: '2026-09-30', hits: 5 },
        { bucket: '2026-10-01', hits: 37 },
      ],
      ip_blacklist: [{ bucket: '2026-10-01', hits: 2 }],
    },
  };

  /** 四笔挂起中的补块请求 → 各自回一坨，再走一轮变更检测把结果画进 DOM */
  const flushBlocks = async (
    dist: unknown,
    perf: unknown,
    dash: unknown = {},
    trend: unknown = {},
  ): Promise<void> => {
    await tick();
    const body: Record<string, unknown> = {
      '/admin/v1/risk/action-distribution': dist,
      '/admin/v1/risk/rule-performance': perf,
      '/admin/v1/risk/dashboard': dash,
      '/admin/v1/risk/hit-trend': trend,
    };
    for (const p of Object.keys(body)) {
      http.expectOne((r) => r.url.split('?')[0] === p).flush({ code: 0, message: 'ok', data: body[p] });
    }
    await tick();
    fixture.detectChanges();
  };

  /** 按卡片标题取一张卡片（同屏有三处 .tiles，只能按标题定位） */
  const card = (title: string): HTMLElement => {
    const el = fixture.nativeElement as HTMLElement;
    const hit = [...el.querySelectorAll('.card')].find(
      (c) => c.querySelector('.card-head')?.textContent?.trim() === title,
    );
    expect(hit, `没找到标题为「${title}」的卡片`).toBeTruthy();
    return hit as HTMLElement;
  };

  /** 文本对不上时给得出人话 */
  const labels = (): string[] =>
    [...(fixture.nativeElement as HTMLElement).querySelectorAll('.tile-label')].map((x) =>
      x.textContent!.trim(),
    );

  beforeEach(() => {
    localStorage.clear();
    use('zh');
    TestBed.configureTestingModule({ providers: [provideHttpClient(), provideHttpClientTesting()] });
    http = TestBed.inject(HttpTestingController);
    fixture = TestBed.createComponent(RiskCharts);
  });

  afterEach(() => {
    try {
      http.verify();
    } finally {
      TestBed.resetTestingModule();
    }
  });

  it('total 的四个数渲染成指标卡（标签走词条，不是字段名）', async () => {
    fixture.componentRef.setInput('raw', OVERVIEW);
    fixture.detectChanges();
    await flushBlocks({ items: [] }, { items: [] });

    // total 在前（头条数字），后面才是 from/to/group_by/block_rate 那几个原始标量
    expect(labels().slice(0, 4)).toEqual(['命中数', '已阻断', '告警', '仅记录']);
    const values = [...(fixture.nativeElement as HTMLElement).querySelectorAll('.tile-value')].map(
      (x) => x.textContent!.trim(),
    );
    expect(values.slice(0, 4)).toEqual(['660', '270', '220', '170']);
  });

  it('动作分布画成条、规则效果里零命中显示 —（不是 0%）', async () => {
    fixture.componentRef.setInput('raw', OVERVIEW);
    fixture.detectChanges();
    await flushBlocks(
      {
        total: 240,
        items: [
          { action: 'log', count: 120, ratio: 50 },
          { action: 'block', count: 40, ratio: 16.67 },
        ],
      },
      {
        items: [
          { name: '高频提现', action: 'block', hits: 50, block_rate: 40, manual_review_rate: 10 },
          { name: '新设备大额', action: 'warn', hits: 0, block_rate: 0, manual_review_rate: 0 },
        ],
      },
    );
    const el = fixture.nativeElement as HTMLElement;

    const bars = [...el.querySelectorAll('.bar-row')].map((r) => ({
      label: r.querySelector('span')!.textContent!.trim(),
      width: (r.querySelector('.fill') as HTMLElement).style.width,
      val: r.querySelector('.val')!.textContent!.trim(),
    }));
    // 条宽用服务端算好的 ratio（占比），不按最大值归一
    expect(bars).toEqual([
      { label: '仅记录', width: '50%', val: '120 / 50%' },
      { label: '拦截', width: '16.67%', val: '40 / 16.67%' },
    ]);

    const rows = [...el.querySelectorAll('tbody tr')].map((tr) =>
      [...tr.querySelectorAll('td')].map((td) => td.textContent!.trim()),
    );
    expect(rows[0]).toEqual(['高频提现', '拦截', '50', '40%', '10%']);
    expect(rows[1]).toEqual(['新设备大额', '告警', '0', '—', '—']);
  });

  /**
   * 钉两件事：① 24h 那五个量与 total 用的是**同一批词**（不许冒出 second 说法）；
   * ② 阻断率补 `%` —— 服务端回的是 float（16.67），裸数字会被读成「16 次」。
   */
  it('近 24h 快照：前五个量复用旧词 + 阻断率带 %，后四个规模量各就各位', async () => {
    fixture.componentRef.setInput('raw', OVERVIEW);
    fixture.detectChanges();
    await flushBlocks({ items: [] }, { items: [] }, DASH, TREND);

    const tiles = [...card('近 24 小时').querySelectorAll('.tile')].map((x) => [
      x.querySelector('.tile-label')!.textContent!.trim(),
      x.querySelector('.tile-value')!.textContent!.trim(),
    ]);
    expect(tiles).toEqual([
      ['命中数', '42'],
      ['已阻断', '7'],
      ['告警', '5'],
      ['仅记录', '30'],
      ['阻断率', '16.67%'],
      ['启用规则', '3'],
      ['规则总数', '8'],
      ['IP 黑名单', '12'],
      ['设备簇', '4'],
    ]);

    // 最近事件走列表（detail 是新词 risk.head.detail，其余复用事件表的表头）
    const heads = [...card('最近事件（24h）').querySelectorAll('thead th')].map((x) =>
      x.textContent!.trim(),
    );
    expect(heads).toEqual(['用户', '类型', '处置', '详情', '时间']);
    const row = [...card('最近事件（24h）').querySelectorAll('tbody td')].map((x) =>
      x.textContent!.trim(),
    );
    expect(row).toEqual(['UH1', 'frequency', '拦截', '60s 内 9 次', '2026-10-01 10:00:00']);
  });

  /**
   * 按规则类型的趋势：服务端回的是**对象**（键序不保证），页面上必须按总命中降序；
   * 只命中过一天的规则照旧成行（**不画线**但数字在）—— 直接过滤会让它从界面上消失。
   */
  it('按类型趋势：按总命中降序，只命中一天的仍成行（无折线、有数字）', async () => {
    fixture.componentRef.setInput('raw', OVERVIEW);
    fixture.detectChanges();
    await flushBlocks({ items: [] }, { items: [] }, DASH, TREND);

    const rows = [...card('按规则类型的命中').querySelectorAll('.spark-row')].map((r) => ({
      name: r.querySelector('.name')!.textContent!.trim(),
      total: r.querySelector('.val')!.textContent!.trim(),
      points: r.querySelector('polyline')!.getAttribute('points'),
    }));
    expect(rows.map((r) => [r.name, r.total])).toEqual([
      ['frequency', '42'],
      ['ip_blacklist', '2'],
    ]);
    // 两点成线；单点回空串（polyline 的 points 属性为空 ⇒ 画不出来，但行还在）
    expect(rows[0]!.points).toMatch(/^0\.00,/);
    expect(rows[1]!.points).toBe('');
  });

  it('父组件换一个新 raw 就重拉一次补块（不是只认第一次）', async () => {
    fixture.componentRef.setInput('raw', OVERVIEW);
    fixture.detectChanges();
    await flushBlocks({ items: [] }, { items: [] });

    fixture.componentRef.setInput('raw', { ...OVERVIEW, total: { hits: 1 } });
    fixture.detectChanges();
    await flushBlocks({ items: [] }, { items: [] });
    // 两次都拉到 ⇒ verify() 才能过；只拉一次的实现在这里就是「请求数不足」
    expect(labels()[0]).toBe('命中数');
  });

  it('raw 置空不重拉（切走标签页那一下不该白打请求）', async () => {
    fixture.componentRef.setInput('raw', OVERVIEW);
    fixture.detectChanges();
    await flushBlocks({ items: [] }, { items: [] });

    fixture.componentRef.setInput('raw', null);
    fixture.detectChanges();
    await tick();
    http.verify(); // 没有多余请求
  });

  /**
   * 变异点：把 `untracked(() => void this.reload())` 去掉包裹（还原成直接调用），
   * 这条立刻红 —— 切语言后 http.verify() 会报出两个没人应答的请求。
   */
  it('切语言只重绘，不重拉补块', async () => {
    fixture.componentRef.setInput('raw', OVERVIEW);
    fixture.detectChanges();
    await flushBlocks({ items: [] }, { items: [] });

    // 覆盖层是按需拉的（`use()` 的 promise 到货才 resolve）⇒ 不等就只会看到英文回落
    await use('ja');
    fixture.detectChanges();
    await tick();

    // 有请求挂着 verify() 就会抛（effect 把语言信号也当成了依赖）
    http.verify();
    expect(labels()[0]).toBe('検知数');
  });
});
