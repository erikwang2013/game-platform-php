/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, computed, inject, signal } from '@angular/core';
import { Api, Page, Row } from '../core/api.service';
import { idOf, json, scalarsOf } from '../core/render';
import { errText } from '../core/util';
import { ListBase } from '../core/list-base';
import { Pager, StateBlock, StatCard, Tabs } from '../components/ui';
import { Table } from '../components/table';

const R = '/admin/v1/';

@Component({
  selector: 'app-risk',
  imports: [StateBlock, StatCard, Table, Pager, Tabs],
  template: `
    <div class="page-head">
      <h1>风险控制</h1>
      <span class="sub">风控总览 / 事件 / 规则 / 设备 / 反作弊</span>
      <div class="spacer"></div>
      @if (tab() !== 'overview') {
        <input
          class="input"
          placeholder="用户 ID / 规则名 / 设备"
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
      [empty]="tab() !== 'overview' && !rows().length"
    >
      @if (tab() === 'overview') {
        @if (scalars().length) {
          <div class="tiles">
            @for (s of scalars(); track s.k) {
              <ui-stat [label]="s.k" [value]="s.v" />
            }
          </div>
        } @else {
          <div class="state">风控总览暂无数据</div>
        }
        @if (raw(); as d) {
          <details class="raw-box">
            <summary>原始响应</summary>
            <pre class="raw">{{ pretty(d) }}</pre>
          </details>
        }
      } @else {
        <div class="card">
          <div class="card-body">
            <ui-table [rows]="rows()" [actions]="actions()" (act)="run($event.row, $event.key)" />
          </div>
        </div>
      }
    </ui-state>

    @if (tab() !== 'overview' && rows().length) {
      <ui-pager [page]="page()" [pages]="pages" [total]="total()" (jump)="go($event)" />
    }
  `,
})
export class Risk extends ListBase<Row> {
  private readonly api = inject(Api);

  protected readonly tabs = [
    { key: 'overview', label: '风控总览' },
    { key: 'users', label: '风险用户' },
    { key: 'events', label: '风险事件' },
    { key: 'rules', label: '风控规则' },
    { key: 'devices', label: '设备' },
    { key: 'anticheat', label: '反作弊' },
  ];
  protected readonly tab = signal('overview');
  protected readonly raw = signal<unknown>(null);

  protected readonly scalars = computed(() => scalarsOf(this.raw()));
  protected readonly actions = computed(() => {
    switch (this.tab()) {
      case 'events':
        return [
          { key: 'confirm', label: '确认风险' },
          { key: 'ignore', label: '忽略' },
        ];
      case 'rules':
        return [{ key: 'toggle', label: '启用/停用' }];
      case 'devices':
        return [
          { key: 'block', label: '拉黑', danger: true },
          { key: 'unblock', label: '解除' },
        ];
      case 'anticheat':
        return [
          { key: 'approve', label: '通过' },
          { key: 'reject', label: '驳回', danger: true },
        ];
      default:
        return [];
    }
  });

  private readonly paths: Record<string, string> = {
    users: R + 'risk/users',
    events: R + 'risk/event/list',
    rules: R + 'risk/rule/list',
    devices: R + 'risk/device/list',
    anticheat: R + 'anticheat/events',
  };

  protected pick(key: string): void {
    this.tab.set(key);
    this.page.set(1);
    void this.load();
  }

  protected pretty(v: unknown): string {
    return json(v);
  }

  protected override async fetch(): Promise<Page<Row>> {
    if (this.tab() === 'overview') {
      try {
        this.raw.set(await this.api.get<unknown>(R + 'risk/overview'));
      } catch (e) {
        this.error.set(errText(e));
      }
      return { list: [], total: 0, page: 1, limit: this.pageSize };
    }
    this.raw.set(null);
    const url = this.paths[this.tab()] ?? this.paths['events']!;
    return this.api.list<Row>(url, {
      page: this.page(),
      page_size: this.pageSize,
      keyword: this.keyword(),
    });
  }

  /** 处置动作：入参字段名未确认，按接口语义直传 id */
  protected async run(row: Row, key: string): Promise<void> {
    const id = idOf(row);
    if (!id) return;
    this.error.set('');
    try {
      if (this.tab() === 'events') {
        await this.api.post(R + 'risk/event/' + id + '/handle', { action: key });
      } else if (this.tab() === 'rules') {
        await this.api.post(R + 'risk/rule/' + id + '/toggle', {});
      } else if (this.tab() === 'devices') {
        await this.api.post(R + 'risk/device/' + (key === 'block' ? 'block' : 'unblock'), {
          device_id: id,
        });
      } else if (this.tab() === 'anticheat') {
        await this.api.post(R + 'anticheat/events/' + id + '/review', { action: key });
      }
      await this.load();
    } catch (e) {
      this.error.set(errText(e));
    }
  }
}
