/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, inject, signal } from '@angular/core';
import { Api, Page, Row } from '../core/api.service';
import { ListBase } from '../core/list-base';
import { Pager, StateBlock, Tabs } from '../components/ui';
import { Table } from '../components/table';

const R = '/admin/v1/';

@Component({
  selector: 'app-content',
  imports: [StateBlock, Table, Pager, Tabs],
  template: `
    <div class="page-head">
      <h1>内容运营</h1>
      <span class="sub">成就 / 活动 / 公告 / 排行榜</span>
      <div class="spacer"></div>
      <input
        class="input"
        placeholder="名称 / ID"
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
export class Content extends ListBase<Row> {
  private readonly api = inject(Api);

  protected readonly tabs = [
    { key: 'achievement', label: '成就' },
    { key: 'activities', label: '活动' },
    { key: 'announcement', label: '公告' },
    { key: 'leaderboard', label: '排行榜' },
  ];
  protected readonly tab = signal('achievement');

  private readonly paths: Record<string, string> = {
    achievement: R + 'achievement/list',
    activities: R + 'activities/list',
    announcement: R + 'announcement/list',
    leaderboard: R + 'leaderboard/list',
  };

  protected pick(key: string): void {
    this.tab.set(key);
    this.page.set(1);
    void this.load();
  }

  protected override fetch(): Promise<Page<Row>> {
    const url = this.paths[this.tab()] ?? this.paths['achievement']!;
    return this.api.list<Row>(url, {
      page: this.page(),
      page_size: this.pageSize,
      keyword: this.keyword(),
    });
  }
}
