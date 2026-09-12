/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, computed, inject, signal } from '@angular/core';
import { Api, Page, Row } from '../core/api.service';
import { idOf } from '../core/render';
import { errText } from '../core/util';
import { ListBase } from '../core/list-base';
import { Pager, StateBlock, Tabs } from '../components/ui';
import { Table } from '../components/table';

const F = '/admin/v1/';

@Component({
  selector: 'app-finance',
  imports: [StateBlock, Table, Pager, Tabs],
  template: `
    <div class="page-head">
      <h1>财务中心</h1>
      <span class="sub">提现审核 / 支付方式 / 提现限额</span>
      <div class="spacer"></div>
      @if (tab() === 'orders') {
        <input
          class="input"
          placeholder="订单号 / 用户 ID"
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
      <div class="card">
        <div class="card-body">
          <ui-table [rows]="rows()" [actions]="actions()" (act)="run($event.row, $event.key)" />
        </div>
      </div>
    </ui-state>

    @if (rows().length) {
      <ui-pager [page]="page()" [pages]="pages" [total]="total()" (jump)="go($event)" />
    }
  `,
})
export class Finance extends ListBase<Row> {
  private readonly api = inject(Api);

  protected readonly tabs = [
    { key: 'orders', label: '提现订单' },
    { key: 'methods', label: '支付方式' },
    { key: 'limits', label: '提现限额' },
  ];
  protected readonly tab = signal('orders');

  protected readonly actions = computed(() => {
    switch (this.tab()) {
      case 'orders':
        return [
          { key: 'approve', label: '通过' },
          { key: 'reject', label: '拒绝', danger: true },
          { key: 'payout', label: '打款' },
        ];
      case 'methods':
        return [{ key: 'toggle', label: '启用/停用' }];
      default:
        return [];
    }
  });

  private readonly paths: Record<string, string> = {
    orders: F + 'withdraw/orders',
    methods: F + 'payment/method/list',
    limits: F + 'withdraw/limits/list',
  };

  protected pick(key: string): void {
    this.tab.set(key);
    this.page.set(1);
    void this.load();
  }

  protected override fetch(): Promise<Page<Row>> {
    const url = this.paths[this.tab()] ?? this.paths['orders']!;
    return this.api.list<Row>(url, {
      page: this.page(),
      page_size: this.pageSize,
      keyword: this.keyword(),
    });
  }

  /** 审核走 PUT /withdraw/review（order_id + action），打款/开关为 POST */
  protected async run(row: Row, key: string): Promise<void> {
    const id = idOf(row);
    if (!id) return;
    this.error.set('');
    try {
      if (key === 'payout') {
        await this.api.post(F + 'withdraw/execute-payout', { id });
      } else if (key === 'toggle') {
        await this.api.post(F + 'payment/method/toggle', { id });
      } else {
        await this.api.request('PUT', F + 'withdraw/review', { order_id: id, action: key });
      }
      await this.load();
    } catch (e) {
      this.error.set(errText(e));
    }
  }
}
