/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { use } from '../core/i18n/i18n';
import { RiskGraph } from './risk-graph';

/**
 * 两个只读图谱端点（GET /risk/graph/{hashid} 与 /risk/graph/clusters）的呈现。三件事会静默坏掉：
 *  1. 节点 status 的 **-1 是服务端哨兵**（用户行已被删），不是「禁用」—— 照数值显示会冒出一列 -1；
 *  2. 边表两端是 hashid，不回填用户名等于给人两串不认得的码；
 *  3. 设备簇的成员服务端只取前 20 个而 account_count 是全量 —— 不说「这是前 N 个」就是假话。
 */
describe('RiskGraph', () => {
  let http: HttpTestingController;
  let fixture: ComponentFixture<RiskGraph>;

  const GRAPH = {
    root: 'UH1',
    nodes: [
      { id: 'UH1', username: 'alice', status: 1, is_root: true },
      { id: 'UH2', username: 'bob', status: 0, is_root: false },
      { id: 'UH3', username: '幽灵', status: -1, is_root: false },
    ],
    edges: [
      { from: 'UH1', to: 'UH2', type: 'same_device', weight: 0.6, occurrences: 1 },
      { from: 'UH2', to: 'UH3', type: 'same_ip', weight: 1.0, occurrences: 1 },
    ],
    cluster_size: 3,
    hops: 2,
    risk_verdict: 'suspicious',
  };

  const CLUSTERS = {
    device_clusters: [
      {
        fp_masked: 'abcd1234****',
        account_count: 5,
        last_seen_at: '2026-10-01 09:00:00',
        members: [
          { id: 'U1', username: 'a' },
          { id: 'U2', username: 'b' },
        ],
      },
      {
        fp_masked: 'beef5678****',
        account_count: 1,
        last_seen_at: '2026-09-30 09:00:00',
        members: [{ id: 'U9', username: 'z' }],
      },
    ],
    link_type_stats: { same_device: 7, same_ip: 3 },
  };

  const el = (): HTMLElement => fixture.nativeElement as HTMLElement;
  const rows = (cardIdx = 0): string[][] =>
    [...el().querySelectorAll('.card')[cardIdx]!.querySelectorAll('tbody tr')].map((tr) =>
      [...tr.querySelectorAll('td')].map((td) => td.textContent!.trim()),
    );
  const tiles = (): string[][] =>
    [...el().querySelectorAll('.tile')].map((x) => [
      x.querySelector('.tile-label')!.textContent!.trim(),
      x.querySelector('.tile-value')!.textContent!.trim(),
    ]);

  const show = (raw: unknown, mode: 'graph' | 'clusters' = 'graph'): void => {
    fixture.componentRef.setInput('raw', raw);
    fixture.componentRef.setInput('mode', mode);
    fixture.detectChanges();
  };

  beforeEach(() => {
    localStorage.clear();
    use('zh');
    TestBed.configureTestingModule({ providers: [provideHttpClient(), provideHttpClientTesting()] });
    http = TestBed.inject(HttpTestingController);
    fixture = TestBed.createComponent(RiskGraph);
  });

  afterEach(() => {
    try {
      http.verify(); // 呈现组件自己不发请求（取数在风控页）
    } finally {
      TestBed.resetTestingModule();
    }
  });

  it('graph：簇大小/跳数/判定三个数 + 节点表（起始账号打勾、-1 是「账号已不存在」）', () => {
    show(GRAPH);

    expect(tiles()).toEqual([
      ['簇大小', '3'],
      ['跳数', '2'],
      ['判定', '可疑'],
    ]);
    // 卡片 0 = 节点表：status 1/0 走 app.enabled/app.disabled，-1 走哨兵词；
    // 「起始账号」列只在根节点上有值，其余是 ui-table 的空值约定（`dash()` → `—`）
    expect(rows(0)).toEqual([
      ['alice', '启用', '✓'],
      ['bob', '停用', '—'],
      ['幽灵', '账号已不存在', '—'],
    ]);
  });

  it('graph：边表两端回填成用户名（不回填就是两串 hashid）', () => {
    show(GRAPH);

    expect(
      [...el().querySelectorAll('.card')[1]!.querySelectorAll('thead th')].map((x) =>
        x.textContent!.trim(),
      ),
    ).toEqual(['起点', '终点', '类型']);
    expect(rows(1)).toEqual([
      ['alice', 'bob', 'same_device'],
      ['bob', '幽灵', 'same_ip'],
    ]);
  });

  /** 判定是服务端枚举：将来多一个取值时显示原文，别把整张卡片吞成空白 */
  it('graph：没登记的判定值原样显示', () => {
    show({ ...GRAPH, risk_verdict: 'weird' });
    expect(tiles()[2]).toEqual(['判定', 'weird']);
  });

  it('clusters：一簇一张卡（成员芯片 + 只给前 N 个时说清）+ 关联类型统计', () => {
    show(CLUSTERS, 'clusters');

    const cards = [...el().querySelectorAll('.card')];
    expect(cards.map((c) => c.querySelector('.card-head')!.textContent!.trim())).toEqual([
      'abcd1234**** · 5 账号数',
      'beef5678**** · 1 账号数',
      '关联类型统计',
    ]);
    expect([...cards[0]!.querySelectorAll('.tag')].map((t) => t.textContent!.trim())).toEqual([
      'a',
      'b',
    ]);
    // 成员 2 个而账号数 5 ⇒ 少给的份额要说出来；第二簇 1/1 ⇒ 不出现这句话
    expect(cards[0]!.textContent).toContain('仅列出前 2 个，共 5 个账号');
    expect(cards[1]!.textContent).not.toContain('仅列出');
    expect(cards[0]!.textContent).toContain('最近出现 2026-10-01 09:00:00');
    expect([...cards[2]!.querySelectorAll('.tag')].map((t) => t.textContent!.trim())).toEqual([
      'same_device × 7',
      'same_ip × 3',
    ]);
  });

  it('raw 为空（刚开抽屉 / 取数失败）⇒ 一张卡都不出，也不留空态块', () => {
    show(null);
    expect(el().querySelectorAll('.card').length).toBe(0);

    show(null, 'clusters');
    expect(el().querySelectorAll('.card').length).toBe(0);
    expect(el().textContent!.trim()).toBe('');
  });
});
