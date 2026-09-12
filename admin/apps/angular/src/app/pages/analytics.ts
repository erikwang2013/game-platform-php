/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, computed, inject, signal } from '@angular/core';
import { Api, Params, Row } from '../core/api.service';
import { json, rowsAny, scalarsOf } from '../core/render';
import { dash, errText, num, pairs } from '../core/util';
import { areaPoints, linePoints } from '../core/chart';
import { StateBlock, StatCard, Tabs } from '../components/ui';
import { Table } from '../components/table';

type Kind = 'kpi' | 'line' | 'bars' | 'funnel' | 'table';

interface Tab {
  key: string;
  label: string;
  path: string;
  kind: Kind;
}

const A = '/admin/v1/analytics/';

@Component({
  selector: 'app-analytics',
  imports: [StateBlock, StatCard, Table, Tabs],
  template: `
    <div class="page-head">
      <h1>数据分析</h1>
      <span class="sub">流量 / 收入 / 留存 / 经济</span>
      <div class="spacer"></div>
      <select class="input" [value]="days()" (change)="setDays($any($event.target).value)">
        <option value="7">近 7 天</option>
        <option value="14">近 14 天</option>
        <option value="30">近 30 天</option>
      </select>
      <button class="btn" (click)="load()">刷新</button>
    </div>

    <ui-tabs [tabs]="tabs" [active]="tab()" (pick)="pick($event)" />

    <ui-state [loading]="loading()" [error]="error()">
      @switch (kind()) {
        @case ('kpi') {
          @if (scalars().length) {
            <div class="tiles">
              @for (s of scalars(); track s.k) {
                <ui-stat [label]="s.k" [value]="s.v" />
              }
            </div>
          } @else {
            <div class="state">该接口暂无标量指标</div>
          }
        }
        @case ('line') {
          @if (points()) {
            <div class="chart">
              <svg viewBox="0 0 100 100" preserveAspectRatio="none">
                <polyline class="grid" points="0,99 100,99" />
                <polygon class="area s1" [attr.points]="area()" />
                <polyline class="line s1" [attr.points]="points()" />
              </svg>
              <div class="axis">
                <span>{{ first() }}</span
                ><span>{{ last() }}</span>
              </div>
            </div>
          } @else {
            <div class="state">暂无时间序列数据</div>
          }
        }
        @case ('bars') {
          @if (series().length) {
            @for (p of series(); track $index) {
              <div class="bar-row">
                <span>{{ p.label }}</span>
                <div class="track"><div class="fill" [style.width.%]="barPct(p.value)"></div></div>
                <span class="val">{{ p.value }}</span>
              </div>
            }
          } @else {
            <div class="state">暂无排行数据</div>
          }
        }
        @case ('funnel') {
          @if (funnel().length) {
            @for (f of funnel(); track $index) {
              <div class="bar-row">
                <span>{{ dash(f['step']) }}</span>
                <div class="track"><div class="fill" [style.width.%]="stepPct(f)"></div></div>
                <span class="val">{{ rate(f) }}</span>
              </div>
            }
          } @else {
            <div class="state">暂无漏斗数据</div>
          }
        }
        @default {
          @if (rows().length) {
            <div class="card">
              <div class="card-body"><ui-table [rows]="rows()" /></div>
            </div>
          } @else if (scalars().length) {
            <div class="tiles">
              @for (s of scalars(); track s.k) {
                <ui-stat [label]="s.k" [value]="s.v" />
              }
            </div>
          } @else {
            <div class="state">该接口暂无数据</div>
          }
        }
      }

      @if (raw(); as d) {
        <details class="raw-box">
          <summary>原始响应</summary>
          <pre class="raw">{{ pretty(d) }}</pre>
        </details>
      }
    </ui-state>
  `,
})
export class Analytics {
  private readonly api = inject(Api);

  /** 模板作用域只认类成员，模块级 import 不可见 */
  protected readonly dash = dash;

  protected readonly tabs: Tab[] = [
    { key: 'overview', label: '总览', path: A + 'overview', kind: 'kpi' },
    { key: 'dau', label: 'DAU 趋势', path: A + 'dau-trend', kind: 'line' },
    { key: 'rank', label: '游戏排行', path: A + 'game-ranking', kind: 'bars' },
    { key: 'funnel', label: '转化漏斗', path: A + 'funnel', kind: 'funnel' },
    { key: 'retention', label: '留存', path: A + 'retention', kind: 'kpi' },
    { key: 'arpu', label: 'ARPU', path: A + 'arpu', kind: 'line' },
    { key: 'revenue', label: '营收', path: A + 'revenue', kind: 'line' },
    { key: 'conversion', label: '转化', path: A + 'conversion', kind: 'bars' },
    { key: 'hourly', label: '分时', path: A + 'hourly-trend', kind: 'line' },
    { key: 'action', label: '行为分布', path: A + 'action-distribution', kind: 'bars' },
    { key: 'probability', label: '概率', path: A + 'probability', kind: 'table' },
    { key: 'economy', label: '经济', path: A + 'economy', kind: 'table' },
  ];

  protected readonly tab = signal('overview');
  protected readonly days = signal(7);
  protected readonly raw = signal<unknown>(null);
  protected readonly loading = signal(true);
  protected readonly error = signal('');

  protected readonly scalars = computed(() => scalarsOf(this.raw()));
  protected readonly rows = computed(() => rowsAny(this.raw(), 'currencies'));
  protected readonly funnel = computed(() => rowsAny(this.raw(), 'funnel', 'steps'));
  protected readonly series = computed(() => pairs(this.raw()));
  protected readonly points = computed(() => linePoints(this.series().map((p) => p.value)));
  protected readonly area = computed(() => areaPoints(this.series().map((p) => p.value)));
  protected readonly first = computed(() => this.series()[0]?.label ?? '');
  protected readonly last = computed(() => {
    const s = this.series();
    return s.length ? s[s.length - 1]!.label : '';
  });

  constructor() {
    void this.load();
  }

  protected current(): Tab {
    return this.tabs.find((t) => t.key === this.tab()) ?? this.tabs[0]!;
  }

  protected kind(): Kind {
    return this.current().kind;
  }

  protected pretty(v: unknown): string {
    return json(v);
  }

  protected setDays(v: string): void {
    this.days.set(Number(v) || 7);
    void this.load();
  }

  protected pick(key: string): void {
    this.tab.set(key);
    void this.load();
  }

  /** 条形宽度：纯绘图几何，非金额计算 */
  protected barPct(v: number): number {
    const max = Math.max(1, ...this.series().map((p) => Math.abs(p.value)));
    return Math.min(100, (Math.abs(v) / max) * 100);
  }

  /** 漏斗条宽：取本步人数占首步的比例（仅画图） */
  protected stepPct(f: Row): number {
    const all = this.funnel();
    const top = all.length ? num(all[0]!['count']) : 0;
    if (!top) return 0;
    return Math.min(100, (num(f['count']) / top) * 100);
  }

  /** rate 后端已给展示字符串（如 "87%"），有就直接用 */
  protected rate(f: Row): string {
    return dash(f['rate']) === '—' ? String(num(f['count'])) : dash(f['rate']);
  }

  protected async load(): Promise<void> {
    const c = this.current();
    this.loading.set(true);
    this.error.set('');
    this.raw.set(null);
    try {
      const params: Params = { days: this.days() };
      this.raw.set(await this.api.get<unknown>(c.path, params));
    } catch (e) {
      this.error.set(errText(e));
    } finally {
      this.loading.set(false);
    }
  }
}
