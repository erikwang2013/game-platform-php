/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, computed, inject, signal } from '@angular/core';
import { Api, Params } from '../core/api.service';
import { json, nested, rowsAny, scalarsOf } from '../core/render';
import { errText, num } from '../core/util';
import { T } from '../core/i18n/i18n';
import { Pager, StateBlock, StatCard } from '../components/ui';
import { Table } from '../components/table';

interface Tab {
  key: string;
  /** 词条键（渲染时过 `| t`；查不到原样显示） */
  label: string;
  path: string;
}

@Component({
  selector: 'app-dashboard',
  imports: [StateBlock, StatCard, Table, Pager, T],
  template: `
    <div class="page-head">
      <h1>{{ 'dashboard.title' | t }}</h1>
      <span class="sub">{{ 'dashboard.subtitle' | t }}</span>
      <div class="spacer"></div>
      <button class="btn" (click)="load()">{{ 'app.refresh' | t }}</button>
    </div>

    <div class="tabs">
      @for (tb of tabs; track tb.key) {
        <button [class.active]="tb.key === tab()" (click)="pick(tb.key)">{{ tb.label | t }}</button>
      }
    </div>

    <ui-state [loading]="loading()" [error]="error()">
      @if (text(); as raw) {
        <div class="card">
          <div class="card-head">{{ 'dashboard.metrics_raw' | t }}</div>
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
            {{ current().label | t }}
            <div class="spacer"></div>
            @if (searchable()) {
              <input
                class="input"
                [placeholder]="'dashboard.search_hint' | t"
                [value]="keyword()"
                (input)="keyword.set($any($event.target).value)"
                (keyup.enter)="search()"
              />
              <button class="btn" (click)="search()">{{ 'app.search' | t }}</button>
            }
          </div>
          <div class="card-body">
            <ui-table [rows]="rows()" />
          </div>
        </div>
      }

      @if (!loading() && !error() && !scalars().length && !rows().length && !text()) {
        <div class="state">{{ 'dashboard.empty_tip' | t }}</div>
      }

      <!-- 操作日志 / 全局检索都是服务端分页的（page+limit / page+per_page），原先写死 page=1：
           第 50 条以后的数据根本取不到 ⇒ 给它俩补上分页器 -->
      @if (searchable() && rows().length) {
        <ui-pager [page]="page()" [pages]="pages()" [total]="total()" (jump)="jump($event)" />
      }

      @if (data(); as d) {
        <details class="raw-box">
          <summary>{{ 'app.raw_response' | t }}</summary>
          <pre class="raw">{{ pretty(d) }}</pre>
        </details>
      }
    </ui-state>
  `,
})
export class Dashboard {
  private readonly api = inject(Api);

  protected readonly tabs: Tab[] = [
    { key: 'overview', label: 'dashboard.tab.overview', path: '/admin/v1/dashboard' },
    { key: 'platform', label: 'dashboard.tab.platform', path: '/admin/v1/dashboard/platform' },
    { key: 'health', label: 'dashboard.tab.health', path: '/health' },
    { key: 'metrics', label: 'dashboard.tab.metrics', path: '/metrics' },
    { key: 'log', label: 'dashboard.tab.log', path: '/admin/v1/log' },
    { key: 'search', label: 'dashboard.tab.search', path: '/admin/v1/search' },
  ];

  protected readonly tab = signal('overview');
  protected readonly keyword = signal('');
  protected readonly data = signal<unknown>(null);
  protected readonly text = signal('');
  protected readonly loading = signal(true);
  protected readonly error = signal('');
  protected readonly page = signal(1);

  /** 两个可检索页签的每页条数（log 读 limit、search 读 per_page，见 load()） */
  private readonly perPage = 50;

  protected readonly scalars = computed(() => scalarsOf(this.data()));
  protected readonly rows = computed(() => rowsAny(this.data(), 'logs', 'results', 'users'));
  protected readonly searchable = computed(() => ['log', 'search'].includes(this.tab()));
  /** 服务端回的总数（两个端点都是 {list, total} ⇒ 分页器能算真页数，不是 list.length 假充） */
  protected readonly total = computed(() => num(nested(this.data(), 'total')));
  protected readonly pages = computed(() => Math.max(1, Math.ceil(this.total() / this.perPage)));

  constructor() {
    void this.load();
  }

  protected current(): Tab {
    return this.tabs.find((t) => t.key === this.tab()) ?? this.tabs[0]!;
  }

  protected pick(key: string): void {
    this.tab.set(key);
    this.page.set(1);
    void this.load();
  }

  /** 查询：关键词变了 ⇒ 回到第 1 页（沿用 ListBase 的口径） */
  protected search(): void {
    this.page.set(1);
    void this.load();
  }

  protected jump(p: number): void {
    const next = Math.min(Math.max(1, p), this.pages());
    if (next === this.page()) return;
    this.page.set(next);
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
      // 两个可检索 tab 的服务端读参口径不同，api.get 又不做别名扇出（page_size/keyword 谁都不认识），
      // 所以按端点各发自己的名字，见 LogController / SearchController：
      //   /admin/v1/log    → limit 分页 + path 模糊过滤（action 是精确匹配，不能收自由文本）
      //   /admin/v1/search → per_page 分页 + q（必填，为空时服务端直接返回空列表）
      const params: Params = !this.searchable()
        ? {}
        : this.tab() === 'search'
          ? { q: this.keyword(), page: this.page(), per_page: this.perPage }
          : { path: this.keyword(), page: this.page(), limit: this.perPage };
      this.data.set(await this.api.get<unknown>(c.path, params));
    } catch (e) {
      this.error.set(errText(e));
    } finally {
      this.loading.set(false);
    }
  }
}
