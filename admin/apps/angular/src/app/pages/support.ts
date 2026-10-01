/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, computed, inject, signal } from '@angular/core';
import { Api, Page, Row } from '../core/api.service';
import { Field } from '../core/crud';
import { idOf, json, kvOf, scalarsOf } from '../core/render';
import { errText } from '../core/util';
import { downloadTransactions } from '../core/export';
import { ListBase } from '../core/list-base';
import { T } from '../core/i18n/i18n';
import { Drawer, Pager, StateBlock, StatCard, Tabs } from '../components/ui';
import { Table } from '../components/table';
import { FormModal } from '../components/form-modal';

const S = '/admin/v1/';

/** 回复正文：TicketController::reply 里 `empty($content)` 直接 422 'Content required'，前端先挡一道 */
const REPLY_FIELDS: Field[] = [
  {
    name: 'content',
    label: 'ticket.reply_content',
    type: 'textarea',
    required: true,
    placeholder: 'ticket.reply_hint',
  },
];

/**
 * admin_id 收的是**数字 id**：`(int) $request->input('admin_id', 0)`，这条路上没有 decodeId。
 * 传 hashid 会被 (int) 静默压成 0，而 0 的语义是「取消指派」—— 界面显示成功、受理人被悄悄清空。
 * 所以先按 /^\d+$/ 挡一道；留空 = 显式取消指派（后端默认值就是 0）。
 */
const ASSIGN_FIELDS: Field[] = [
  {
    name: 'admin_id',
    label: 'ticket.admin_id',
    type: 'text',
    placeholder: 'ticket.admin_id_hint',
  },
];

@Component({
  selector: 'app-support',
  imports: [StateBlock, StatCard, Table, Pager, Tabs, Drawer, FormModal, T],
  template: `
    <div class="page-head">
      <h1>{{ 'ticket.title' | t }}</h1>
      <span class="sub">{{ 'ticket.subtitle' | t }}</span>
      <div class="spacer"></div>
      @if (tab() === 'ticket') {
        <input
          class="input"
          [placeholder]="'ticket.search_hint' | t"
          [value]="keyword()"
          (input)="keyword.set($any($event.target).value)"
          (keyup.enter)="search()"
        />
        <button class="btn" (click)="search()">{{ 'app.search' | t }}</button>
      }
      <!-- 只挂在报表页：两个导出（按天聚合的日报 / 全平台流水）都与工单是两回事 -->
      @if (tab() === 'report') {
        <button class="btn" [disabled]="exporting()" (click)="exportReport()">
          {{ (exporting() ? 'app.exporting' : 'report.export') | t }}
        </button>
        <button class="btn" [disabled]="exporting()" (click)="exportTransactions()">
          {{ (exporting() ? 'app.exporting' : 'export.transactions') | t }}
        </button>
      }
      <button class="btn" (click)="load()">{{ 'app.refresh' | t }}</button>
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
            <summary>{{ 'report.raw_title' | t }}</summary>
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

    @if (paged() && rows().length) {
      <ui-pager [page]="page()" [pages]="pages" [total]="total()" (jump)="go($event)" />
    }

    <!-- title 是**值**不是词条键（ui-drawer 的 title() 不过 t 管道，与 ui-form 的 title 不同）⇒ 这里先查表 -->
    <ui-drawer [open]="detail() !== null" [title]="'ticket.detail' | t" (close)="detail.set(null)">
      @if (detail(); as d) {
        <dl class="kv">
          @for (p of info(); track p.label) {
            <dt>{{ p.label }}</dt>
            <dd>{{ p.value }}</dd>
          } @empty {
            <dt>{{ 'ticket.empty_hint' | t }}</dt>
            <dd>{{ 'ticket.no_data' | t }}</dd>
          }
        </dl>
        <div class="row-actions">
          <button class="btn btn-primary" (click)="openAct(d, 'reply')">{{ 'ticket.reply' | t }}</button>
          <button class="btn" (click)="openAct(d, 'assign')">{{ 'ticket.assign' | t }}</button>
          <button class="btn danger" (click)="closeTicket(d)">{{ 'ticket.close' | t }}</button>
        </div>
      }
    </ui-drawer>

    <ui-form
      [open]="formOpen()"
      [title]="formTitle()"
      [fields]="formFields()"
      [value]="null"
      [error]="formError()"
      [saving]="saving()"
      (save)="submitAct($event)"
      (close)="closeForm()"
    />
  `,
})
export class Support extends ListBase<Row> {
  private readonly api = inject(Api);

  protected readonly tabs = [
    { key: 'ticket', label: 'ticket.tab' },
    { key: 'report', label: 'report.tab' },
  ];
  protected readonly tab = signal('ticket');

  /**
   * 只有工单列表是分页的（/ticket/list：page+limit，默认 20）。报表端点是 /report/daily ——
   * 回 `{start,end,rows}` 是按天聚合的结果，没有分页参数、也不该有「第 2 页」。
   */
  protected readonly paged = computed(() => this.tab() !== 'report');

  protected readonly detail = signal<Row | null>(null);
  protected readonly raw = signal<unknown>(null);
  /** 报表导出中（按钮禁用 + 文案切换）。走 Api.download，不经过 rows/loading，不打断表格 */
  protected readonly exporting = signal(false);

  /**
   * 回复 / 指派共用这个弹框。工单是**动作型**（reply/close/assign 三个 POST），没有实体可增删改：
   * 本树底座 CrudPage 的 extra 动作必须挂在 crud() 上，而 crud() 非空就会带出「编辑 / 删除」
   * 两个行内按钮，工单没有对应的 PUT/DELETE 路由 —— 硬塞就是两个点了会 404 的按钮。
   * 所以这里只借它的表单组件（ui-form），弹框状态自己拿着，不新造底座。
   */
  protected readonly formOpen = signal(false);
  protected readonly formTitle = signal('');
  protected readonly formFields = signal<Field[]>(REPLY_FIELDS);
  protected readonly formError = signal('');
  protected readonly saving = signal(false);
  private formRow: Row | null = null;
  private formAct = '';

  protected readonly info = computed(() => kvOf(this.detail()));
  protected readonly scalars = computed(() => scalarsOf(this.raw()));

  protected pick(key: string): void {
    this.tab.set(key);
    this.page.set(1);
    this.detail.set(null);
    this.raw.set(null);
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

  /**
   * 导出日报 —— GET /report/export?format=xlsx（ReportController::export，回 .xlsx 附件）。
   *
   * **必须显式传 format=xlsx**：该参数的缺省值是 `'excel'`，而分支只认 `'xlsx'`，
   * 走缺省就掉进 CSV 分支 —— 文件名是 .csv 但按钮写着 .xlsx，运营拿到手才发现。
   *
   * 不传 start/end：服务端缺省最近 30 天，与上面 /report/daily 那张表用的是**同一个**
   * normalizeDateRange 缺省（同一控制器），所以导出的就是屏幕上这份，不会多也不会少。
   */
  protected async exportReport(): Promise<void> {
    this.exporting.set(true);
    this.error.set('');
    try {
      await this.api.download('GET', S + 'report/export', { format: 'xlsx' });
    } catch (e) {
      this.error.set(errText(e));
    } finally {
      this.exporting.set(false);
    }
  }

  /**
   * 导出全平台流水 —— POST /export/transactions（ExportController::exportTransactions）。
   * 与旁边的日报是**两张表**：这个是 `game_user_transaction` 流水明细（服务端 orderBy created_at
   * desc limit 10000），日报是按天聚合。没有参数可传：那个端点只认一个可选的 type，
   * 而它的值域全仓没有能列出来的端点 ⇒ 摆个下拉就是「看着能筛、筛出来是空」的假控件。
   */
  protected async exportTransactions(): Promise<void> {
    this.exporting.set(true);
    this.error.set('');
    try {
      await downloadTransactions(this.api);
    } catch (e) {
      this.error.set(errText(e));
    } finally {
      this.exporting.set(false);
    }
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

  /** 确认/弹框标题里的对象标识：标题（subject），退回用户名 + 工单号 */
  protected who(row: Row): string {
    return String(row['subject'] ?? '') || `${row['user_name'] ?? ''} ${idOf(row)}`.trim();
  }

  protected openAct(row: Row, act: string): void {
    this.formRow = row;
    this.formAct = act;
    const reply = act === 'reply';
    this.formFields.set(reply ? REPLY_FIELDS : ASSIGN_FIELDS);
    this.formTitle.set(
      this.i18n.t('ticket.form_title', {
        action: this.i18n.t(reply ? 'ticket.reply' : 'ticket.assign'),
        name: this.who(row),
      }),
    );
    this.formError.set('');
    this.formOpen.set(true);
  }

  protected closeForm(): void {
    this.formOpen.set(false);
    this.formRow = null;
    this.formAct = '';
  }

  protected async submitAct(values: Row): Promise<void> {
    const row = this.formRow;
    const id = row ? idOf(row) : '';
    if (!id || !this.formAct) return;
    const body: Row = {};
    if (this.formAct === 'reply') {
      const content = String(values['content'] ?? '').trim();
      if (!content) {
        this.formError.set(this.i18n.t('ticket.reply_required'));
        return;
      }
      body['content'] = content;
    } else {
      const raw = String(values['admin_id'] ?? '').trim();
      if (raw && !/^\d+$/.test(raw)) {
        this.formError.set(this.i18n.t('ticket.admin_id_invalid'));
        return;
      }
      // 数字串原样发：snowflake 是 19 位，超出 JS 安全整数，Number() 会四舍五入到隔壁的 ID，
      // 后端 (int) 转字符串是精确的。留空发 0（= 显式取消指派）。
      body['admin_id'] = raw === '' ? 0 : raw;
    }
    this.saving.set(true);
    this.formError.set('');
    try {
      await this.api.post(S + 'ticket/' + id + '/' + this.formAct, body);
      this.closeForm();
      this.detail.set(null);
      await this.load();
    } catch (e) {
      // 服务端原因（'Ticket is closed'、'Content required'）原样留在框里
      this.formError.set(errText(e));
    } finally {
      this.saving.set(false);
    }
  }

  /** 关闭：不可逆（关闭后 reply 会被后端拒收），二次确认带标题 */
  protected async closeTicket(row: Row): Promise<void> {
    const id = idOf(row);
    if (!id || !confirm(this.i18n.t('ticket.close_confirm_target', { name: this.who(row) }))) return;
    this.error.set('');
    try {
      await this.api.post(S + 'ticket/' + id + '/close');
      this.detail.set(null);
      await this.load();
    } catch (e) {
      this.error.set(errText(e));
    }
  }
}
