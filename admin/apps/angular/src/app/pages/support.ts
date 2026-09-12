/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, computed, inject, signal } from '@angular/core';
import { Api, Page, Row } from '../core/api.service';
import { idOf, json, kvOf, scalarsOf } from '../core/render';
import { errText } from '../core/util';
import { ListBase } from '../core/list-base';
import { Drawer, Pager, StateBlock, StatCard, Tabs } from '../components/ui';
import { Table } from '../components/table';

const S = '/admin/v1/';

@Component({
  selector: 'app-support',
  imports: [StateBlock, StatCard, Table, Pager, Tabs, Drawer],
  template: `
    <div class="page-head">
      <h1>客服工单</h1>
      <span class="sub">工单 / 报表</span>
      <div class="spacer"></div>
      @if (tab() === 'ticket') {
        <input
          class="input"
          placeholder="工单号 / 用户 / 标题"
          [value]="keyword()"
          (input)="keyword.set($any($event.target).value)"
          (keyup.enter)="search()"
        />
        <button class="btn" (click)="search()">查询</button>
      }
      <button class="btn" (click)="load()">刷新</button>
    </div>

    <ui-tabs [tabs]="tabs" [active]="tab()" (pick)="pick($event)" />

    <ui-state [loading]="loading()" [error]="error()" [empty]="!rows().length">
      @if (tab() === 'report') {
        @if (scalars().length) {
          <div class="tiles">
            @for (s of scalars(); track s.k) {
              <ui-stat [label]="s.k" [value]="s.v" />
            }
          </div>
        }
        @if (raw(); as d) {
          <details class="raw-box">
            <summary>报表汇总原始响应</summary>
            <pre class="raw">{{ pretty(d) }}</pre>
          </details>
        }
      }
      <div class="card">
        <div class="card-body">
          <ui-table [rows]="rows()" [clickable]="tab() === 'ticket'" (pick)="open($event)" />
        </div>
      </div>
    </ui-state>

    @if (rows().length) {
      <ui-pager [page]="page()" [pages]="pages" [total]="total()" (jump)="go($event)" />
    }

    <ui-drawer [open]="detail() !== null" title="工单详情" (close)="detail.set(null)">
      @if (detail(); as d) {
        <dl class="kv">
          @for (p of info(); track p.label) {
            <dt>{{ p.label }}</dt>
            <dd>{{ p.value }}</dd>
          } @empty {
            <dt>提示</dt>
            <dd>该工单暂无可展示字段</dd>
          }
        </dl>
      }
    </ui-drawer>
  `,
})
export class Support extends ListBase<Row> {
  private readonly api = inject(Api);

  protected readonly tabs = [
    { key: 'ticket', label: '工单' },
    { key: 'report', label: '报表' },
  ];
  protected readonly tab = signal('ticket');
  protected readonly detail = signal<Row | null>(null);
  protected readonly raw = signal<unknown>(null);

  protected readonly info = computed(() => kvOf(this.detail()));
  protected readonly scalars = computed(() => scalarsOf(this.raw()));

  protected pick(key: string): void {
    this.tab.set(key);
    this.page.set(1);
    this.detail.set(null);
    void this.load();
  }

  protected pretty(v: unknown): string {
    return json(v);
  }

  protected override async fetch(): Promise<Page<Row>> {
    if (this.tab() === 'report') {
      try {
        this.raw.set(await this.api.get<unknown>(S + 'report/summary'));
      } catch (e) {
        this.error.set(errText(e));
      }
      return this.api.list<Row>(S + 'report/daily', {
        page: this.page(),
        page_size: this.pageSize,
      });
    }
    this.raw.set(null);
    return this.api.list<Row>(S + 'ticket/list', {
      page: this.page(),
      page_size: this.pageSize,
      keyword: this.keyword(),
    });
  }

  /** 详情：先展示列表行，再拉 ticket/{hashid} 覆盖 */
  protected async open(row: Row): Promise<void> {
    const id = idOf(row);
    this.detail.set(row);
    if (!id) return;
    try {
      const d = await this.api.get<unknown>(S + 'ticket/' + id);
      if (d && typeof d === 'object' && !Array.isArray(d)) this.detail.set(d as Row);
    } catch {
      // 详情取不到就展示列表行本身，不阻塞抽屉
    }
  }
}
