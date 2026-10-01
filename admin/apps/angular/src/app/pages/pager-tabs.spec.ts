/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { Type } from '@angular/core';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { Content } from './content';
import { Dashboard } from './dashboard';
import { Games } from './games';
import { Infra } from './infra';
import { Marketing } from './marketing';
import { Risk } from './risk';
import { Settings } from './settings';
import { Support } from './support';
import { Users } from './users';

/**
 * 分页器只跟着**真分页**的端点走。
 * 两个方向都要钉：整表端点（`->get()`/`->all()`，响应里没有 total）挂分页器 = 给出一个假的第 2 页；
 * 真分页端点漏挂 = 第 N 页起的数据永远取不到。首屏读数不是判据（服务端不返回 total 时
 * api.list 拿 list.length 顶数，两种端点都能凑出「2 页」）⇒ 这里判的是**页签上的 DOM**。
 */
describe('页签该不该有分页器', () => {
  let http: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting()],
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

  const tick = (): Promise<void> => new Promise((resolve) => setTimeout(resolve, 0));

  /**
   * 每个被测端点的**真实响应形状**（注意信封：`data` 里才是列表 —— api.list 拿到的是拆过信封的 data）。
   * 裸数组端点（权限树 / 区服）的 data 就是数组，**不能**把数组直接当响应体灌：那样信封整个是数组，
   * code!==0 ⇒ ApiError ⇒ rows 空 ⇒ 「没有分页器」的断言变成永真（变异逃逸）。
   */
  const payload = (url: string): object => {
    const path = url.split('?')[0]!;
    if (path.endsWith('/permission')) {
      return {
        code: 0,
        message: 'ok',
        data: [{ id: 'P1', name: '系统', parent_id: 0, children: [{ id: 'P2', name: '用户' }] }],
      };
    }
    if (path.endsWith('/game/server/list')) {
      return { code: 0, message: 'ok', data: [{ id: 'S1', name: '一区' }] };
    }
    if (path.endsWith('/report/daily')) {
      return {
        code: 0,
        message: 'ok',
        data: { start: '2026-09-01', end: '2026-09-30', rows: [{ date: '2026-09-01', orders: 3 }] },
      };
    }
    if (path.endsWith('/report/summary')) return { code: 0, message: 'ok', data: { orders: 3 } };
    return { code: 0, message: 'ok', data: { list: [{ id: 'X1', name: '样例' }], total: 42 } };
  };

  /** 反复灌到没有未决请求为止：有的页签是「先取 A 再取 B」的串行链 */
  const flushAll = async (): Promise<void> => {
    for (let i = 0; i < 4; i++) {
      const reqs = http.match(() => true);
      if (!reqs.length) break;
      for (const r of reqs) r.flush(payload(r.request.url));
      await tick();
    }
  };

  type Page = {
    tab: { set(v: string): void };
    load(): Promise<void>;
  };

  /**
   * 切到某页签后看 DOM 里有没有 ui-pager。
   * 响应一律灌「有数据」：行数为 0 时列表页本来就不渲染分页器，测不出该不该有。
   * prep 只在有前置条件的页签上用（区服的 game_id 必填，不填前端压根不发请求）。
   */
  const hasPager = async (
    type: Type<unknown>,
    tab: string,
    prep?: (ci: unknown) => void,
  ): Promise<boolean> => {
    const f: ComponentFixture<unknown> = TestBed.createComponent(type);
    f.detectChanges();
    await tick();
    await flushAll();
    const ci = f.componentInstance as unknown as Page;
    prep?.(ci);
    ci.tab.set(tab);
    void ci.load();
    await tick();
    await flushAll();
    f.detectChanges();
    const out = !!(f.nativeElement as HTMLElement).querySelector('ui-pager');
    // 行数为 0 时「整表端点」与「真分页端点」在 DOM 上长得一模一样（都没有分页器）⇒
    // 断言会退化成永真。先把「这个页签确实有数据」钉住，否则本用例是自我安慰。
    const n = (f.componentInstance as unknown as { rows(): unknown[] }).rows().length;
    f.destroy();
    if (!n) throw new Error(`「${tab}」页签一行都没灌进去，分页器断言无意义`);
    return out;
  };

  it('内容：成就（整表 ->get()，无 total）没有；活动（page+limit）有', async () => {
    expect(await hasPager(Content, 'achievement')).toBe(false);
    expect(await hasPager(Content, 'activities')).toBe(true);
    expect(await hasPager(Content, 'announcement')).toBe(true);
    expect(await hasPager(Content, 'leaderboard')).toBe(true);
  });

  it('游戏：分类（整表）与区服（裸数组、连分页参数都不看）没有；游戏列表有', async () => {
    expect(await hasPager(Games, 'category')).toBe(false);
    expect(
      await hasPager(Games, 'server', (c) =>
        (c as { gameId: { set(v: string): void } }).gameId.set('G1'),
      ),
    ).toBe(false);
    expect(await hasPager(Games, 'game')).toBe(true);
  });

  it('营销：VIP 等级（整表，且被别处当下拉数据源）没有；券列表有', async () => {
    expect(await hasPager(Marketing, 'vip')).toBe(false);
    expect(await hasPager(Marketing, 'coupon')).toBe(true);
  });

  it('基础设施：CDN 厂商（整表）没有；国家配置有', async () => {
    expect(await hasPager(Infra, 'cdn')).toBe(false);
    expect(await hasPager(Infra, 'country')).toBe(true);
  });

  it('客服：报表（按天聚合，没有第 2 页）没有；工单有', async () => {
    expect(await hasPager(Support, 'report')).toBe(false);
    expect(await hasPager(Support, 'ticket')).toBe(true);
  });

  it('设置：权限（裸树、无 total）没有；配置与角色有', async () => {
    expect(await hasPager(Settings, 'permission')).toBe(false);
    expect(await hasPager(Settings, 'config')).toBe(true);
    expect(await hasPager(Settings, 'role')).toBe(true);
  });

  it('用户：两个页签都是 page+limit 真分页', async () => {
    expect(await hasPager(Users, 'list')).toBe(true);
    expect(await hasPager(Users, 'identity')).toBe(true);
  });

  /**
   * 风控总览**没法用 DOM 证伪**：fetch() 对 /risk/overview 恒回 `{list: [], total: 0}`（聚合值走
   * 原始响应面板），行数永远是 0 ⇒ `rows().length` 单人就能挡住分页器，模板里那个
   * `tab() !== 'overview'` 是叠在它上面的第二道，删掉也观察不到差异。不编造响应来凑一条断言
   * —— 那条「绿」只会是自我安慰。此处只钉有行可数的那个页签。
   */
  it('风控：风险事件（page+size）有分页器', async () => {
    expect(await hasPager(Risk, 'events')).toBe(true);
  });

  it('仪表盘：操作日志（page+limit）有 —— 原先写死 page=1，第 50 条以后取不到', async () => {
    expect(await hasPager(Dashboard, 'log')).toBe(true);
    // 总览是聚合快照（没有 page 参数可言）
    expect(await hasPager(Dashboard, 'overview')).toBe(false);
  });
});
