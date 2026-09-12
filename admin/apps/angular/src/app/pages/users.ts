/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, computed, inject, signal } from '@angular/core';
import { Api, Page, Row } from '../core/api.service';
import { idOf, kvOf } from '../core/render';
import { errText } from '../core/util';
import { ListBase } from '../core/list-base';
import { Drawer, Pager, StateBlock, Tabs } from '../components/ui';
import { Table } from '../components/table';

const U = '/admin/v1/';

@Component({
  selector: 'app-users',
  imports: [StateBlock, Table, Pager, Tabs, Drawer],
  template: `
    <div class="page-head">
      <h1>用户管理</h1>
      <span class="sub">用户列表 / 实名审核</span>
      <div class="spacer"></div>
      <input
        class="input"
        placeholder="用户 ID / 用户名 / 手机号"
        [value]="keyword()"
        (input)="keyword.set($any($event.target).value)"
        (keyup.enter)="search()"
      />
      <button class="btn" (click)="search()">查询</button>
    </div>

    <ui-tabs [tabs]="tabs" [active]="tab()" (pick)="pick($event)" />

    <ui-state [loading]="loading()" [error]="error()" [empty]="!rows().length">
      <div class="card">
        <div class="card-body">
          <ui-table
            [rows]="rows()"
            [clickable]="tab() === 'list'"
            [actions]="actions()"
            (pick)="open($event)"
            (act)="run($event.row, $event.key)"
          />
        </div>
      </div>
    </ui-state>

    @if (rows().length) {
      <ui-pager [page]="page()" [pages]="pages" [total]="total()" (jump)="go($event)" />
    }

    <ui-drawer [open]="detail() !== null" title="用户详情" (close)="detail.set(null)">
      @if (detail(); as d) {
        <dl class="kv">
          @for (p of info(); track p.label) {
            <dt>{{ p.label }}</dt>
            <dd>{{ p.value }}</dd>
          } @empty {
            <dt>提示</dt>
            <dd>该用户暂无可展示字段</dd>
          }
        </dl>
        <div class="row-actions">
          <button class="btn" (click)="status(d, 'normal')">解封</button>
          <button class="btn danger" (click)="status(d, 'banned')">封禁</button>
          <button class="btn danger" (click)="destroy(d)">注销账号</button>
        </div>
      }
    </ui-drawer>
  `,
})
export class Users extends ListBase<Row> {
  private readonly api = inject(Api);

  protected readonly tabs = [
    { key: 'list', label: '用户列表' },
    { key: 'identity', label: '实名审核' },
  ];
  protected readonly tab = signal('list');
  protected readonly detail = signal<Row | null>(null);

  protected readonly info = computed(() => kvOf(this.detail()));
  protected readonly actions = computed(() =>
    this.tab() === 'identity'
      ? [
          { key: 'approve', label: '通过' },
          { key: 'reject', label: '拒绝', danger: true },
        ]
      : [],
  );

  protected pick(key: string): void {
    this.tab.set(key);
    this.page.set(1);
    this.detail.set(null);
    void this.load();
  }

  protected override fetch(): Promise<Page<Row>> {
    const url = this.tab() === 'identity' ? U + 'identity/list' : U + 'platform/user/list';
    return this.api.list<Row>(url, {
      page: this.page(),
      page_size: this.pageSize,
      keyword: this.keyword(),
    });
  }

  protected async open(row: Row): Promise<void> {
    const id = idOf(row);
    this.detail.set(row);
    if (!id) return;
    try {
      const d = await this.api.get<unknown>(U + 'platform/user/' + id);
      if (d && typeof d === 'object' && !Array.isArray(d)) this.detail.set(d as Row);
    } catch {
      // 详情取不到就展示列表行本身，不阻塞抽屉
    }
  }

  protected async run(row: Row, key: string): Promise<void> {
    const id = idOf(row);
    if (!id) return;
    this.error.set('');
    try {
      await this.api.request('PUT', U + 'identity/review', { id, action: key });
      this.detail.set(null);
      await this.load();
    } catch (e) {
      this.error.set(errText(e));
    }
  }

  protected async status(row: Row, value: string): Promise<void> {
    const id = idOf(row);
    if (!id) return;
    try {
      await this.api.post(U + 'user/batch/status', { ids: [id], status: value });
      this.detail.set(null);
      await this.load();
    } catch (e) {
      this.error.set(errText(e));
    }
  }

  protected async destroy(row: Row): Promise<void> {
    const id = idOf(row);
    if (!id || !confirm('确认注销该账号？该操作不可撤销。')) return;
    try {
      await this.api.post(U + 'user/batch/destroy', { ids: [id] });
      this.detail.set(null);
      await this.load();
    } catch (e) {
      this.error.set(errText(e));
    }
  }
}
