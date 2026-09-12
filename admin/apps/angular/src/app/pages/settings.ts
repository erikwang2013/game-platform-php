/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, computed, inject, signal } from '@angular/core';
import { Api, Page, Row } from '../core/api.service';
import { json, scalarsOf } from '../core/render';
import { errText } from '../core/util';
import { ListBase } from '../core/list-base';
import { Pager, StateBlock, StatCard, Tabs } from '../components/ui';
import { Table } from '../components/table';

const S = '/admin/v1/';

@Component({
  selector: 'app-settings',
  imports: [StateBlock, StatCard, Table, Pager, Tabs],
  template: `
    <div class="page-head">
      <h1>系统设置</h1>
      <span class="sub">配置 / 角色 / 权限 / 指标 / 健康</span>
      <div class="spacer"></div>
      @if (isList()) {
        <input
          class="input"
          placeholder="键名 / 名称 / ID"
          [value]="keyword()"
          (input)="keyword.set($any($event.target).value)"
          (keyup.enter)="search()"
        />
        <button class="btn" (click)="search()">查询</button>
      }
      <button class="btn" (click)="load()">刷新</button>
    </div>

    <ui-tabs [tabs]="tabs" [active]="tab()" (pick)="pick($event)" />

    <ui-state
      [loading]="loading()"
      [error]="error()"
      [empty]="isList() ? !rows().length : !text() && !scalars().length"
    >
      @if (tab() === 'metrics') {
        <div class="card">
          <div class="card-body">
            <pre class="raw">{{ text() || '暂无指标' }}</pre>
          </div>
        </div>
      } @else if (tab() === 'health') {
        @if (scalars().length) {
          <div class="tiles">
            @for (s of scalars(); track s.k) {
              <ui-stat [label]="s.k" [value]="s.v" />
            }
          </div>
        }
        @if (raw(); as d) {
          <details class="raw-box">
            <summary>健康检查原始响应</summary>
            <pre class="raw">{{ pretty(d) }}</pre>
          </details>
        }
      } @else {
        <div class="card">
          <div class="card-body">
            <ui-table [rows]="rows()" />
          </div>
        </div>
      }
    </ui-state>

    @if (isList() && rows().length) {
      <ui-pager [page]="page()" [pages]="pages" [total]="total()" (jump)="go($event)" />
    }
  `,
})
export class Settings extends ListBase<Row> {
  private readonly api = inject(Api);

  protected readonly tabs = [
    { key: 'config', label: '系统配置' },
    { key: 'role', label: '角色' },
    { key: 'permission', label: '权限' },
    { key: 'metrics', label: '监控指标' },
    { key: 'health', label: '健康检查' },
  ];
  protected readonly tab = signal('config');
  protected readonly raw = signal<unknown>(null);
  protected readonly text = signal('');

  protected readonly scalars = computed(() => scalarsOf(this.raw()));

  private readonly paths: Record<string, string> = {
    config: S + 'config',
    role: S + 'role',
    permission: S + 'permission',
  };

  protected isList(): boolean {
    return this.tab() in this.paths;
  }

  protected pick(key: string): void {
    this.tab.set(key);
    this.page.set(1);
    this.raw.set(null);
    this.text.set('');
    void this.load();
  }

  protected pretty(v: unknown): string {
    return json(v);
  }

  protected override async fetch(): Promise<Page<Row>> {
    const tab = this.tab();
    if (tab === 'metrics') {
      try {
        this.text.set(await this.api.getText('/metrics'));
      } catch (e) {
        this.error.set(errText(e));
      }
      return { list: [], total: 0, page: 1, limit: this.pageSize };
    }
    if (tab === 'health') {
      try {
        this.raw.set(await this.api.get<unknown>('/health'));
      } catch (e) {
        this.error.set(errText(e));
      }
      return { list: [], total: 0, page: 1, limit: this.pageSize };
    }
    this.raw.set(null);
    return this.api.list<Row>(this.paths[tab] ?? this.paths['config']!, {
      page: this.page(),
      page_size: this.pageSize,
      keyword: this.keyword(),
    });
  }
}
