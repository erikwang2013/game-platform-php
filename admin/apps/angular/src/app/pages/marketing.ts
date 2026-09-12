/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, computed, inject, signal } from '@angular/core';
import { Api, Page, Row } from '../core/api.service';
import { idOf, kvOf } from '../core/render';
import { errText } from '../core/util';
import { ListBase } from '../core/list-base';
import { Drawer, Pager, StateBlock, Tabs } from '../components/ui';
import { Table } from '../components/table';

const M = '/admin/v1/';

@Component({
  selector: 'app-marketing',
  imports: [StateBlock, Table, Pager, Tabs, Drawer],
  template: `
    <div class="page-head">
      <h1>营销中心</h1>
      <span class="sub">优惠券 / VIP 等级</span>
      <div class="spacer"></div>
      <input
        class="input"
        placeholder="券码 / 名称 / ID"
        [value]="keyword()"
        (input)="keyword.set($any($event.target).value)"
        (keyup.enter)="search()"
      />
      <button class="btn" (click)="search()">查询</button>
      <button class="btn" (click)="load()">刷新</button>
    </div>

    <ui-tabs [tabs]="tabs" [active]="tab()" (pick)="pick($event)" />

    <ui-state [loading]="loading()" [error]="error()" [empty]="!rows().length">
      <div class="card">
        <div class="card-body">
          <ui-table [rows]="rows()" [clickable]="tab() === 'coupon'" (pick)="open($event)" />
        </div>
      </div>
    </ui-state>

    @if (rows().length) {
      <ui-pager [page]="page()" [pages]="pages" [total]="total()" (jump)="go($event)" />
    }

    <ui-drawer [open]="detail() !== null" title="优惠券统计" (close)="close()">
      @if (detail(); as d) {
        <dl class="kv">
          @for (p of info(); track p.label) {
            <dt>{{ p.label }}</dt>
            <dd>{{ p.value }}</dd>
          } @empty {
            <dt>提示</dt>
            <dd>{{ statsLoading() ? '统计加载中…' : '该券暂无可展示统计' }}</dd>
          }
        </dl>
      }
    </ui-drawer>
  `,
})
export class Marketing extends ListBase<Row> {
  private readonly api = inject(Api);

  protected readonly tabs = [
    { key: 'coupon', label: '优惠券' },
    { key: 'vip', label: 'VIP 等级' },
  ];
  protected readonly tab = signal('coupon');
  protected readonly detail = signal<Row | null>(null);
  protected readonly stats = signal<unknown>(null);
  protected readonly statsLoading = signal(false);

  protected readonly info = computed(() => kvOf(this.stats()));

  private readonly paths: Record<string, string> = {
    coupon: M + 'coupon/list',
    vip: M + 'vip/level/list',
  };

  protected pick(key: string): void {
    this.tab.set(key);
    this.page.set(1);
    this.close();
    void this.load();
  }

  protected close(): void {
    this.detail.set(null);
    this.stats.set(null);
    this.statsLoading.set(false);
  }

  protected override fetch(): Promise<Page<Row>> {
    const url = this.paths[this.tab()] ?? this.paths['coupon']!;
    return this.api.list<Row>(url, {
      page: this.page(),
      page_size: this.pageSize,
      keyword: this.keyword(),
    });
  }

  /** 详情：先展示列表行，再拉 coupon/{hashid}/stats 覆盖统计面板 */
  protected async open(row: Row): Promise<void> {
    const id = idOf(row);
    this.detail.set(row);
    this.stats.set(null);
    if (!id) return;
    this.statsLoading.set(true);
    try {
      this.stats.set(await this.api.get<unknown>(M + 'coupon/' + id + '/stats'));
    } catch (e) {
      this.error.set(errText(e));
    } finally {
      this.statsLoading.set(false);
    }
  }
}
