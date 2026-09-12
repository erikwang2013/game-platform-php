/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, inject, signal } from '@angular/core';
import { Api, Page, Row } from '../core/api.service';
import { ListBase } from '../core/list-base';
import { Pager, StateBlock, Tabs } from '../components/ui';
import { Table } from '../components/table';

const I = '/admin/v1/';

@Component({
  selector: 'app-infra',
  imports: [StateBlock, Table, Pager, Tabs],
  template: `
    <div class="page-head">
      <h1>基础设施</h1>
      <span class="sub">CDN 厂商 / 国家配置</span>
      <div class="spacer"></div>
      <input
        class="input"
        placeholder="名称 / 国家 / ID"
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
          <ui-table [rows]="rows()" />
        </div>
      </div>
    </ui-state>

    @if (rows().length) {
      <ui-pager [page]="page()" [pages]="pages" [total]="total()" (jump)="go($event)" />
    }
  `,
})
export class Infra extends ListBase<Row> {
  private readonly api = inject(Api);

  protected readonly tabs = [
    { key: 'cdn', label: 'CDN 厂商' },
    { key: 'country', label: '国家配置' },
  ];
  protected readonly tab = signal('cdn');

  private readonly paths: Record<string, string> = {
    cdn: I + 'cdn/provider/list',
    country: I + 'country/config/list',
  };

  protected pick(key: string): void {
    this.tab.set(key);
    this.page.set(1);
    void this.load();
  }

  protected override fetch(): Promise<Page<Row>> {
    const url = this.paths[this.tab()] ?? this.paths['cdn']!;
    return this.api.list<Row>(url, {
      page: this.page(),
      page_size: this.pageSize,
      keyword: this.keyword(),
    });
  }
}
