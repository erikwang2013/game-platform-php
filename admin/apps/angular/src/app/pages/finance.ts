/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, computed, signal } from '@angular/core';
import { Page, Row } from '../core/api.service';
import { Crud, CrudPage, Field } from '../core/crud';
import { T, t } from '../core/i18n/i18n';
import { idOf } from '../core/render';
import { dash, enabledLabel, errText, label, num } from '../core/util';
import { Pager, StateBlock, StatCard, Tabs } from '../components/ui';
import { Table } from '../components/table';
import { FormModal } from '../components/form-modal';
import {
  COUNTRY_CODES,
  LIMIT_FIELDS,
  METHOD_FIELDS,
  ORDER_ACTS,
  ORDER_STATUS,
  ORDER_STATUS_LABEL,
  PAYOUT_STATUS_LABEL,
  SET_FIELDS,
} from './finance-fields';

const F = '/admin/v1/';
const W = F + 'withdraw/';

@Component({
  selector: 'app-finance',
  imports: [StateBlock, StatCard, Table, Pager, Tabs, FormModal, T],
  template: `
    <div class="page-head">
      <h1>{{ 'fin.title' | t }}</h1>
      <span class="sub">{{ 'fin.subtitle' | t }}</span>
      <div class="spacer"></div>
      @if (tab() === 'orders') {
        <!-- select 不能绑 [value]（绑定早于 @for 的 option，预选会被吞）⇒ 逐项 [selected] -->
        <select class="input" (change)="setStatus($any($event.target).value)">
          @for (s of ORDER_STATUS; track s.value) {
            <option [value]="s.value" [selected]="status() === s.value">{{ s.label | t }}</option>
          }
        </select>
        <button class="btn" [disabled]="!pending().length" (click)="batch('approve')">
          {{ 'withdraw.batch_approve' | t: { n: pending().length } }}
        </button>
        <button class="btn danger" [disabled]="!pending().length" (click)="batch('reject')">
          {{ 'withdraw.batch_reject' | t: { n: pending().length } }}
        </button>
        <!-- 行数写进按钮名：/export/pdf 不取数、也不是全量，导的就是眼前这一页 -->
        <button
          class="btn"
          [disabled]="!rows().length || busyExport()"
          (click)="exportPdf('withdraw.orders', heads(), rows())"
        >
          {{ 'export.pdf_page' | t: { count: rows().length } }}
        </button>
      }
      <button class="btn" (click)="load()">{{ 'app.refresh' | t }}</button>
      @if (tab() === 'limits') {
        <button class="btn btn-primary" (click)="openSetForm()">
          {{ 'withdraw.reset_all' | t }}
        </button>
      } @else if (writable()) {
        <button class="btn btn-primary" (click)="openCreate()">+ {{ 'app.create' | t }}</button>
      }
    </div>

    <ui-tabs [tabs]="tabs" [active]="tab()" (pick)="pick($event)" />

    @if (note()) {
      <div [class]="noteErr() ? 'alert' : 'notice'">{{ note() }}</div>
    }

    <ui-state [loading]="loading()" [error]="error()" [empty]="empty()">
      @if (tab() === 'switch') {
        <div class="card">
          <div class="card-body">
            <div class="tiles">
              <ui-stat
                [label]="'withdraw.global_switch' | t"
                [value]="(switchOn() ? 'withdraw.switch_on' : 'withdraw.switch_off') | t"
              />
            </div>
            <div class="row-actions">
              <button
                class="btn btn-primary"
                [disabled]="switchOn() || switchBusy()"
                (click)="setSwitch(true)"
              >
                {{ 'withdraw.switch_enable' | t }}
              </button>
              <button
                class="btn danger"
                [disabled]="!switchOn() || switchBusy()"
                (click)="setSwitch(false)"
              >
                {{ 'withdraw.switch_disable' | t }}
              </button>
            </div>
          </div>
        </div>
      } @else {
        <div class="card">
          <div class="card-body">
            <ui-table
              [rows]="rows()"
              [heads]="heads()"
              [actions]="actions()"
              (act)="run($event.row, $event.key)"
            />
          </div>
        </div>
      }
    </ui-state>

    <!-- 订单列表是唯一分页的（list/limits 端点一次返回全部，methods 也不分页）⇒ 只在这里出分页器 -->
    @if (tab() === 'orders' && rows().length) {
      <ui-pager [page]="page()" [pages]="pages" [total]="total()" (jump)="go($event)" />
    }

    <ui-form
      [open]="formOpen()"
      [title]="formTitle()"
      [fields]="formFields()"
      [value]="formValue()"
      [error]="formError()"
      [saving]="saving()"
      (save)="submit($event)"
      (close)="closeForm()"
    />
  `,
})
export class Finance extends CrudPage {
  protected readonly tabs = [
    { key: 'orders', label: 'withdraw.orders' },
    { key: 'switch', label: 'withdraw.switch' },
    { key: 'limits', label: 'withdraw.limits' },
    { key: 'methods', label: 'payment.methods' },
  ];
  protected readonly tab = signal('orders');
  protected readonly ORDER_STATUS = ORDER_STATUS;
  protected readonly status = signal('');
  /** 动作回执（服务端 message 或失败原因）：**就地**显示，不把列表打成错误态 */
  protected readonly note = signal('');
  protected readonly noteErr = signal(false);
  protected readonly switchOn = signal(false);
  protected readonly switchBusy = signal(false);
  /** 「全局限额重置」表单开关：与行内编辑共用同一个 ui-form，只是字段集与端点不同 */
  private readonly setForm = signal(false);

  /** 本页 status=pending 的行：批量审核的选中集（见 batch() 的注释） */
  protected readonly pending = computed(() =>
    this.rows().filter((r) => String(r['status']) === 'pending'),
  );

  protected readonly heads = computed((): Record<string, string> => {
    switch (this.tab()) {
      case 'orders':
        return {
          order_no: 'withdraw.order_no',
          user_name: 'withdraw.user',
          platform_amount: 'withdraw.platform_token',
          fiat_amount: 'withdraw.fiat_amount',
          currency: 'withdraw.currency',
          method: 'withdraw.method',
          status_label: 'withdraw.status',
          payout_status_label: 'withdraw.payout_status',
          review_note: 'withdraw.note',
          created_at: 'withdraw.submit_time',
        };
      case 'limits':
        return {
          user_level: 'withdraw.limit_level',
          single_min: 'withdraw.single_min',
          single_max: 'withdraw.single_max',
          daily_limit: 'withdraw.daily_limit',
          monthly_limit: 'withdraw.monthly_limit',
          fee_pct: 'withdraw.fee_pct',
          fee_max: 'withdraw.fee_max',
          auto_approve_threshold: 'withdraw.auto_threshold',
        };
      case 'methods':
        return {
          name: 'payment.name',
          type: 'payment.type',
          provider: 'payment.provider',
          status_label: 'payment.status',
          sort: 'payment.sort',
          currency: 'payment.currency',
          min_amount: 'payment.min_amount',
          max_amount: 'payment.max_amount',
          countries: 'payment.countries',
        };
      default:
        return {};
    }
  });

  /** 开关标签页没有行 ⇒ 不走「暂无数据」，出自己的卡片 */
  protected readonly empty = computed(() => (this.tab() === 'switch' ? false : !this.rows().length));

  protected pick(key: string): void {
    this.tab.set(key);
    this.page.set(1);
    this.note.set('');
    this.setForm.set(false);
    void this.load();
  }

  /** 状态筛选：status 是 orders 端点唯一认的过滤参数（keyword 它不认），改了要回到第 1 页 */
  protected setStatus(v: string): void {
    this.status.set(v);
    this.search();
  }

  protected override async fetch(): Promise<Page<Row>> {
    const tab = this.tab();
    if (tab === 'switch') {
      try {
        this.switchOn.set(this.isOn(await this.api.get<Row>(W + 'switch')));
      } catch (e) {
        this.error.set(errText(e));
      }
      return { list: [], total: 0, page: 1, limit: this.pageSize };
    }
    if (tab === 'limits') return this.api.list<Row>(W + 'limits/list');
    if (tab === 'methods') {
      const res = await this.api.list<Row>(F + 'payment/method/list');
      // status 是 TINYINT 0/1（`game_payment_method.status`）⇒ 表格那列看 status_label，
      // 原值留着（表单预填/行内启停都读它）
      return {
        ...res,
        list: res.list.map((r) => ({ ...r, status_label: enabledLabel(r['status']) })),
      };
    }
    const res = await this.api.list<Row>(W + 'orders', {
      page: this.page(),
      page_size: this.pageSize,
      // status 是 orders 端点唯一的过滤参数（它不认 keyword）
      status: this.status(),
    });
    // 用户名在嵌套的 user 对象里（{id, username}，encodeIds 过的）；表格只认平铺标量 ⇒ 摊成一列
    return {
      ...res,
      list: res.list.map((r) => ({
        ...r,
        user_name: this.userName(r),
        // 订单状态与打款状态是后端英文枚举（pending/approved/…）⇒ 各自摊平一列译文。
        // **原值原样留着**：行内动作按 `status === 'pending'` 出按钮、批量审核按它挑选中集、
        // 导出与二次确认文案也读它 —— 改了原值就是改了行为，不只是改了显示。
        status_label: t(label(ORDER_STATUS_LABEL, r['status'])),
        payout_status_label: t(label(PAYOUT_STATUS_LABEL, r['payout_status'])),
      })),
    };
  }

  /**
   * 各标签页的写操作描述。
   * 订单：只有行内动作（ends 空着 ⇒ 不出编辑/删除/新建），动作在 extra()。
   * 限额：创建端点 = 「全局限额重置」（POST limits/set），UPDATE 是逐档 PUT，没有 DELETE ⇒ 不出删除。
   */
  protected override crud(): Crud | null {
    const tab = this.tab();
    if (tab === 'orders') {
      return { noun: 'withdraw.noun.order', fields: [], ends: {}, extra: ORDER_ACTS };
    }
    if (tab === 'limits') {
      return {
        noun: 'withdraw.noun.limit',
        fields: this.setForm() ? SET_FIELDS : LIMIT_FIELDS,
        ends: { create: W + 'limits/set', update: (id) => W + 'limits/' + id },
        label: (row) => String(row['user_level'] ?? idOf(row)),
      };
    }
    if (tab === 'methods') {
      return {
        noun: 'payment.noun',
        fields: this.methodFields(),
        statused: true,
        label: (row) => String(row['name'] ?? idOf(row)),
        ends: {
          create: F + 'payment/method/create',
          update: (id) => F + 'payment/method/' + id,
          remove: (id) => F + 'payment/method/' + id,
          toggle: F + 'payment/method/toggle',
        },
      };
    }
    return null;
  }

  /**
   * 可见国家的选项 = 基表 ∪ 列表里出现过的码。
   * 表外的码（如 'FR'）不会因此丢：ui-form 的 multi() 把当前值补成置顶勾选项，
   * 编辑存量行不会误当成「取消勾选」；只是**新建**时选不到从没出现过的码 —— 需要时往基表加一行。
   */
  private countryOpts(): { value: string; label: string }[] {
    const set = new Set(COUNTRY_CODES);
    for (const r of this.rows()) {
      const cs = r['countries'];
      if (Array.isArray(cs)) for (const c of cs) set.add(String(c));
    }
    return [...set].map((v) => ({ value: v, label: v }));
  }

  private methodFields(): Field[] {
    const opts = this.countryOpts();
    return METHOD_FIELDS.map((f) => (f.name === 'countries' ? { ...f, options: opts } : f));
  }

  /** 全局限额重置：借 base 的 create 路径（POST 到 ends.create），只是把字段集换掉 */
  protected openSetForm(): void {
    this.setForm.set(true);
    this.formValue.set(null);
    this.formTitle.set(t('withdraw.reset_title'));
    this.formError.set('');
    this.formOpen.set(true);
  }

  protected override closeForm(): void {
    this.setForm.set(false);
    super.closeForm();
  }

  protected override async submit(values: Row): Promise<void> {
    if (this.setForm() && !confirm(t('withdraw.reset_confirm'))) {
      return;
    }
    await super.submit(values);
  }

  /** 全局开关：PUT 的**响应里就是新状态**，读它（+ 服务端 message），不做乐观更新 */
  protected async setSwitch(on: boolean): Promise<void> {
    this.note.set('');
    this.noteErr.set(false);
    this.switchBusy.set(true);
    try {
      const { data, message } = await this.api.envelope<Row>('PUT', W + 'switch', {
        enabled: on ? 1 : 0,
      });
      this.switchOn.set(this.isOn(data));
      this.note.set(message || t(on ? 'withdraw.switch_opened' : 'withdraw.switch_closed'));
    } catch (e) {
      this.noteErr.set(true);
      this.note.set(errText(e));
    } finally {
      this.switchBusy.set(false);
    }
  }

  /**
   * 批量审核（approve/reject）。选中集 = **当前页里 status=pending 的行**：
   * 本树 ui-table 没有行选择原语，与其让运营手输一列 hashid（错一个就是另一笔钱），
   * 不如把「本页待审核」这件事说清楚 —— 确认框逐条列出订单号与金额，自己可以核；
   * 服务端对每笔仍是原子的（WHERE status='pending'，已处理的跳过），客户端这层只是预览不是判据。
   */
  protected async batch(action: 'approve' | 'reject'): Promise<void> {
    const rows = this.pending();
    if (!rows.length) return;
    const lines = rows
      .slice(0, 8)
      .map((r) => t('withdraw.batch_line', { id: this.orderId(r), money: this.money(r) }));
    const more = rows.length > 8 ? t('withdraw.batch_more', { n: rows.length }) : '';
    const verb = t(action === 'approve' ? 'withdraw.approve' : 'withdraw.batch_reject_verb');
    const ask = t('withdraw.batch_confirm', {
      action: verb,
      n: rows.length,
      lines: lines.join('\n'),
      more,
    });
    if (!confirm(ask)) {
      return;
    }
    this.note.set('');
    this.noteErr.set(false);
    try {
      const ids = rows.map((r) => idOf(r)).filter(Boolean);
      const { message } = await this.api.envelope('POST', W + 'batch-review', { ids, action });
      this.note.set(message || t('withdraw.batch_done'));
    } catch (e) {
      this.noteErr.set(true);
      this.note.set(errText(e));
    } finally {
      // 成败都以服务端为准回读：被拒的那几笔在服务端仍是 pending
      await this.load();
    }
  }

  /** crud().extra 的落点：提现订单的动作（值域见 ORDER_ACTS 的注释） */
  protected override async extra(row: Row, key: string): Promise<void> {
    // 收据是**只读导出**：POST /export/receipt 回的是 PDF 附件、不是信封，也不翻订单状态
    // ⇒ 不走下面那条「动钱先二次确认、再读服务端 message」的路（它没有 message 可读）
    if (key === 'receipt') return this.receipt(row);
    const call = this.orderCall(row, key);
    if (!call) return;
    if (!confirm(this.orderConfirm(row, key))) return;
    this.note.set('');
    this.noteErr.set(false);
    try {
      const { message } = await call();
      this.note.set(this.noteLine(row, message || t('withdraw.act_done')));
    } catch (e) {
      // 动作失败是**动作结果**（后端拒绝 / 渠道报错），不是列表加载失败：
      // 进横幅，别把整张表打成错误态（运营还得靠这张表接着处理下一笔）
      this.noteErr.set(true);
      this.note.set(this.noteLine(row, errText(e)));
    }
  }

  /**
   * 导出提现订单的电子收据 —— POST /export/receipt，入参 {type:'withdraw', order_id:hashid}。
   *
   * 该端点成功回 `response()->download()` 的 PDF，**校验失败却回信封**（ExportController::receipt
   * 的 422 分支）—— Api.download 已按 content-type 分流：JSON 就当错误抛，不会把一坨错误 JSON
   * 存成 .pdf 让人打不开。所以这里只接两种结果：文件下来了（静默，浏览器自己有下载提示），
   * 或者抛错（进横幅，带订单号，运营知道是哪一笔）。
   */
  private async receipt(row: Row): Promise<void> {
    const id = idOf(row);
    if (!id) return;
    this.note.set('');
    this.noteErr.set(false);
    try {
      await this.api.download('POST', F + 'export/receipt', { type: 'withdraw', order_id: id });
    } catch (e) {
      this.noteErr.set(true);
      this.note.set(this.noteLine(row, errText(e)));
    }
  }

  /** 动作端点。review 的三个 action 共用一个 PUT（值域 approve|reject|confirm） */
  private orderCall(row: Row, key: string): (() => Promise<{ message: string }>) | null {
    const id = idOf(row);
    if (!id) return null;
    switch (key) {
      case 'approve':
      case 'reject':
      case 'confirm':
        return () => this.api.envelope('PUT', W + 'review', { order_id: id, action: key });
      case 'payout':
        return () => this.api.envelope('POST', W + 'execute-payout', { order_id: id });
      case 'sync':
        return () => this.api.envelope('POST', W + 'sync-payout', { order_id: id });
      default:
        return null;
    }
  }

  /** 二次确认文案：资金动作必须能认出是哪一笔、多少钱（金额原样字符串，不经 Number） */
  private orderConfirm(row: Row, key: string): string {
    const who = t('withdraw.who', { no: this.orderId(row), money: this.money(row) });
    switch (key) {
      case 'approve':
        return t('withdraw.approve_confirm', { who });
      case 'reject':
        return t('withdraw.reject_confirm', { who });
      case 'confirm':
        return t('withdraw.confirm_confirm', { who });
      case 'payout':
        return t('withdraw.execute_confirm', { who });
      default:
        return t('withdraw.sync_confirm', { who });
    }
  }

  /** 动作回执行：`订单号：服务端 message`（模板与语言都在词条里，别在调用处拼中文冒号） */
  private noteLine(row: Row, text: string): string {
    return t('withdraw.note_line', { id: this.orderId(row), text });
  }

  /** 订单标识：订单号（列表里人认的是它）带上 hashid（端点要的是它） */
  private orderId(row: Row): string {
    const no = String(row['order_no'] ?? '');
    const id = idOf(row);
    return no ? `${no}/${id}` : id;
  }

  /** 金额一律 dash(原样字符串)：DECIMAL 读回来就是精确的十进制文本，不经 Number */
  private money(row: Row): string {
    return t('withdraw.money', {
      platform: dash(row['platform_amount']),
      fiat: dash(row['fiat_amount']),
      currency: dash(row['currency']),
    });
  }

  private userName(row: Row): string {
    const u = row['user'];
    if (u && typeof u === 'object' && !Array.isArray(u)) return String((u as Row)['username'] ?? '');
    return String(row['user_name'] ?? '');
  }

  /** 开关读数：enabled 是布尔、status 是 0/1，两种都吃（同一个方法同一个响应） */
  private isOn(data: Row): boolean {
    return data['enabled'] === true || num(data['status']) === 1;
  }
}
