/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, inject, signal } from '@angular/core';
import { Api, Page, Row } from '../core/api.service';
import { ListBase } from '../core/list-base';
import { Pager, StateBlock, Tabs } from '../components/ui';
import { Table } from '../components/table';

const G = '/admin/v1/game/';

@Component({
  selector: 'app-games',
  imports: [StateBlock, Table, Pager, Tabs],
  template: `
    <div class="page-head">
      <h1>游戏管理</h1>
      <span class="sub">游戏 / 分类 / 区服</span>
      <div class="spacer"></div>
      <input
        class="input"
        placeholder="游戏名 / 标识"
        [value]="keyword()"
        (input)="keyword.set($any($event.target).value)"
        (keyup.enter)="search()"
      />
      <button class="btn" (click)="search()">查询</button>
    </div>

    <ui-tabs [tabs]="tabs" [active]="tab()" (pick)="pick($event)" />

    <ui-state [loading]="loading()" [error]="error()" [empty]="!rows().length">
      <div class="card">
        <div class="card-body"><ui-table [rows]="rows()" /></div>
      </div>
    </ui-state>

    @if (rows().length) {
      <ui-pager [page]="page()" [pages]="pages" [total]="total()" (jump)="go($event)" />
    }
  `,
})
export class Games extends ListBase<Row> {
  private readonly api = inject(Api);

  protected readonly tabs = [
    { key: 'game', label: '游戏列表' },
    { key: 'category', label: '游戏分类' },
    { key: 'server', label: '区服' },
  ];
  protected readonly tab = signal('game');

  private readonly paths: Record<string, string> = {
    game: G + 'list',
    category: G + 'category/list',
    server: G + 'server/list',
  };

  protected pick(key: string): void {
    this.tab.set(key);
    this.page.set(1);
    void this.load();
  }

  protected override async fetch(): Promise<Page<Row>> {
    // ponytail: 货币只读列表后端未提供（仅 POST /game/currency/manage 写接口），故不设该标签页
    const url = this.paths[this.tab()]!;
    return this.api.list<Row>(url, {
      page: this.page(),
      page_size: this.pageSize,
      keyword: this.keyword(),
    });
  }
}
