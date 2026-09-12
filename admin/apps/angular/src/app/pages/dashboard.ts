/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, computed, inject, signal } from '@angular/core';
import { Api, Params, Row } from '../core/api.service';
import { json, rowsAny, scalarsOf } from '../core/render';
import { errText } from '../core/util';
import { StateBlock, StatCard } from '../components/ui';
import { Table } from '../components/table';

interface Tab {
  key: string;
  label: string;
  path: string;
}

@Component({
  selector: 'app-dashboard',
  imports: [StateBlock, StatCard, Table],
  template: `
    <div class="page-head">
      <h1>仪表盘</h1>
      <span class="sub">实时统计 / 平台概览 / 健康与日志</span>
      <div class="spacer"></div>
      <button class="btn" (click)="load()">刷新</button>
    </div>

    <div class="tabs">
      @for (t of tabs; track t.key) {
        <button [class.active]="t.key === tab()" (click)="pick(t.key)">{{ t.label }}</button>
      }
    </div>

    <ui-state [loading]="loading()" [error]="error()">
      @if (text(); as raw) {
        <div class="card">
          <div class="card-head">指标原文（Prometheus text format）</div>
          <div class="card-body">
            <pre class="raw">{{ raw }}</pre>
          </div>
        </div>
      }

      @if (scalars().length) {
        <div class="tiles">
          @for (s of scalars(); track s.k) {
            <ui-stat [label]="s.k" [value]="s.v" />
          }
        </div>
      }

      @if (rows().length) {
        <div class="card">
          <div class="card-head">
            {{ current().label }}
            <div class="spacer"></div>
            @if (searchable()) {
              <input
                class="input"
                placeholder="关键词"
                [value]="keyword()"
                (input)="keyword.set($any($event.target).value)"
                (keyup.enter)="load()"
              />
              <button class="btn" (click)="load()">查询</button>
            }
          </div>
          <div class="card-body">
            <ui-table [rows]="rows()" />
          </div>
        </div>
      }

      @if (!loading() && !error() && !scalars().length && !rows().length && !text()) {
        <div class="state">该接口暂无数据</div>
      }

      @if (data(); as d) {
        <details class="raw-box">
          <summary>原始响应</summary>
          <pre class="raw">{{ pretty(d) }}</pre>
        </details>
      }
    </ui-state>
  `,
})
export class Dashboard {
  private readonly api = inject(Api);

  protected readonly tabs: Tab[] = [
    { key: 'overview', label: '总览', path: '/admin/v1/dashboard' },
    { key: 'platform', label: '平台', path: '/admin/v1/dashboard/platform' },
    { key: 'health', label: '健康', path: '/health' },
    { key: 'metrics', label: '指标', path: '/metrics' },
    { key: 'log', label: '操作日志', path: '/admin/v1/log' },
    { key: 'search', label: '全局检索', path: '/admin/v1/search' },
  ];

  protected readonly tab = signal('overview');
  protected readonly keyword = signal('');
  protected readonly data = signal<unknown>(null);
  protected readonly text = signal('');
  protected readonly loading = signal(true);
  protected readonly error = signal('');

  protected readonly scalars = computed(() => scalarsOf(this.data()));
  protected readonly rows = computed(() => rowsAny(this.data(), 'logs', 'results', 'users'));
  protected readonly searchable = computed(() => ['log', 'search'].includes(this.tab()));

  constructor() {
    void this.load();
  }

  protected current(): Tab {
    return this.tabs.find((t) => t.key === this.tab()) ?? this.tabs[0]!;
  }

  protected pick(key: string): void {
    this.tab.set(key);
    void this.load();
  }

  protected pretty(v: unknown): string {
    return json(v);
  }

  protected async load(): Promise<void> {
    const c = this.current();
    this.loading.set(true);
    this.error.set('');
    this.data.set(null);
    this.text.set('');
    try {
      if (c.key === 'metrics') {
        this.text.set(await this.api.getText(c.path));
        return;
      }
      const params: Params = this.searchable()
        ? { keyword: this.keyword(), page: 1, page_size: 50 }
        : {};
      this.data.set(await this.api.get<unknown>(c.path, params));
    } catch (e) {
      this.error.set(errText(e));
    } finally {
      this.loading.set(false);
    }
  }
}
