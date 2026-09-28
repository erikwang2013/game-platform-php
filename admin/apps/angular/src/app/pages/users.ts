/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, computed, inject, signal } from '@angular/core';
import { Api, Page, Row } from '../core/api.service';
import { idOf, kvOf } from '../core/render';
import { errText, num } from '../core/util';
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

  /**
   * 平台用户封禁/解封 —— 走 PUT /platform/user/{hashid}（PlatformUserController::update，
   * 写的是平台 user 表，status 收 int 0/1）。
   *
   * 原先走 POST /user/batch/status 是错的：那是【管理员】端点（认 AdminUser，Apidoc 标注
   * "批量启用或禁用管理员用户"），我们却把平台用户的 hashid 递进去。且它当时把 status 前置转型
   * —— `(int)'banned'` 与 `(int)'normal'` 都等于 0，封禁与解封一起落成 0 = 禁用；
   * 真问题不是"落成相反的状态"，而是【不该静默解释非法输入】
   * （admin/docs/API.md:754 明写非 0/1 的 status 应当 422），加上 count 报的是请求条数
   * 而非受影响行数，于是界面显示成功、实际可能一行都没改。
   * 后端两点已修：UserController::batchStatus 改先原样比白名单再转 int（口径见
   * UserBatchAffectedRowsTest）。
   */
  protected async status(row: Row, value: string): Promise<void> {
    const id = idOf(row);
    if (!id) return;
    this.error.set('');
    const want = value === 'banned' ? 0 : 1;
    try {
      await this.api.request('PUT', U + 'platform/user/' + id, { status: want });
    } catch (e) {
      this.error.set(errText(e));
      return;
    }
    this.detail.set(null);
    await this.load();
    // 成功以回读到的真实状态为准，不以"请求发出去了"为准
    const after = this.rows().find((r) => idOf(r) === id);
    if (!after) {
      this.error.set('操作已提交，但该用户已不在当前页，请刷新确认');
    } else if (num(after['status']) !== want) {
      this.error.set(`状态未生效：服务端仍为 ${num(after['status'])}`);
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
