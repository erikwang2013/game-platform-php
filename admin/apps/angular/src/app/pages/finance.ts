/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, computed, signal } from '@angular/core';
import { Page, Row } from '../core/api.service';
import { Crud, CrudPage, Field } from '../core/crud';
import { idOf } from '../core/render';
import { dash, errText, num } from '../core/util';
import { Pager, StateBlock, StatCard, Tabs } from '../components/ui';
import { Table } from '../components/table';
import { FormModal } from '../components/form-modal';
import {
  COUNTRY_CODES,
  LIMIT_FIELDS,
  METHOD_FIELDS,
  ORDER_ACTS,
  ORDER_STATUS,
  SET_FIELDS,
} from './finance-fields';

const F = '/admin/v1/';
const W = F + 'withdraw/';

@Component({
  selector: 'app-finance',
  imports: [StateBlock, StatCard, Table, Pager, Tabs, FormModal],
  template: `
    <div class="page-head">
      <h1>财务中心</h1>
      <span class="sub">提现订单 / 提现开关 / 阶梯限额 / 支付方式</span>
      <div class="spacer"></div>
      @if (tab() === 'orders') {
        <!-- select 不能绑 [value]（绑定早于 @for 的 option，预选会被吞）⇒ 逐项 [selected] -->
        <select class="input" (change)="setStatus($any($event.target).value)">
          @for (s of ORDER_STATUS; track s.value) {
            <option [value]="s.value" [selected]="status() === s.value">{{ s.label }}</option>
          }
        </select>
        <button class="btn" [disabled]="!pending().length" (click)="batch('approve')">
          批量通过（{{ pending().length }}）
        </button>
        <button class="btn danger" [disabled]="!pending().length" (click)="batch('reject')">
          批量驳回（{{ pending().length }}）
        </button>
      }
      <button class="btn" (click)="load()">刷新</button>
      @if (tab() === 'limits') {
        <button class="btn btn-primary" (click)="openSetForm()">全局限额重置</button>
      } @else if (writable()) {
        <button class="btn btn-primary" (click)="openCreate()">+ 新建</button>
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
              <ui-stat label="全局提现开关" [value]="switchOn() ? '已开启' : '已关闭'" />
            </div>
            <div class="row-actions">
              <button class="btn btn-primary" [disabled]="switchOn() || switchBusy()" (click)="setSwitch(true)">
                开启提现
              </button>
              <button class="btn danger" [disabled]="!switchOn() || switchBusy()" (click)="setSwitch(false)">
                关闭提现
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
    { key: 'orders', label: '提现订单' },
    { key: 'switch', label: '提现开关' },
    { key: 'limits', label: '阶梯限额' },
    { key: 'methods', label: '支付方式' },
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
          order_no: '订单号',
          user_name: '用户',
          platform_amount: '平台币',
          fiat_amount: '法币',
          currency: '币种',
          method: '方式',
          status: '状态',
          payout_status: '打款状态',
          review_note: '审核备注',
          created_at: '申请时间',
        };
      case 'limits':
        return {
          user_level: '档位',
          single_min: '单笔最低',
          single_max: '单笔最高',
          daily_limit: '日限额',
          monthly_limit: '月限额',
          fee_pct: '手续费率%',
          fee_max: '手续费上限',
          auto_approve_threshold: '自动审批阈值',
        };
      case 'methods':
        return {
          name: '名称',
          type: '类型',
          provider: '提供商',
          status: '状态(0停用/1启用)',
          sort: '排序',
          currency: '限定币种',
          min_amount: '最小充值额',
          max_amount: '最大充值额',
          countries: '可见国家',
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
    if (tab === 'methods') return this.api.list<Row>(F + 'payment/method/list');
    const res = await this.api.list<Row>(W + 'orders', {
      page: this.page(),
      page_size: this.pageSize,
      // status 是 orders 端点唯一的过滤参数（它不认 keyword）
      status: this.status(),
    });
    // 用户名在嵌套的 user 对象里（{id, username}，encodeIds 过的）；表格只认平铺标量 ⇒ 摊成一列
    return { ...res, list: res.list.map((r) => ({ ...r, user_name: this.userName(r) })) };
  }

  /**
   * 各标签页的写操作描述。
   * 订单：只有行内动作（ends 空着 ⇒ 不出编辑/删除/新建），动作在 extra()。
   * 限额：创建端点 = 「全局限额重置」（POST limits/set），UPDATE 是逐档 PUT，没有 DELETE ⇒ 不出删除。
   */
  protected override crud(): Crud | null {
    const tab = this.tab();
    if (tab === 'orders') {
      return { noun: '提现订单', fields: [], ends: {}, extra: ORDER_ACTS };
    }
    if (tab === 'limits') {
      return {
        noun: '阶梯限额',
        fields: this.setForm() ? SET_FIELDS : LIMIT_FIELDS,
        ends: { create: W + 'limits/set', update: (id) => W + 'limits/' + id },
        label: (row) => String(row['user_level'] ?? idOf(row)),
      };
    }
    if (tab === 'methods') {
      return {
        noun: '支付方式',
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
    this.formTitle.set('全局限额重置（写穿全部档位）');
    this.formError.set('');
    this.formOpen.set(true);
  }

  protected override closeForm(): void {
    this.setForm.set(false);
    super.closeForm();
  }

  protected override async submit(values: Row): Promise<void> {
    if (
      this.setForm() &&
      !confirm(
        '确认用填写值覆盖全部档位（default/verified/vip）的限额？留空项保持不变；最低提现金额写的是各档 single_min。',
      )
    ) {
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
      this.note.set(message || (on ? '提现已开启' : '提现已关闭'));
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
    const lines = rows.slice(0, 8).map((r) => `　${this.orderId(r)}：${this.money(r)}`);
    const more = rows.length > 8 ? `\n　…等共 ${rows.length} 笔` : '';
    const verb = action === 'approve' ? '通过' : '驳回（每笔都会把平台币退回用户余额）';
    if (!confirm(`确认批量${verb}本页 ${rows.length} 笔待审核提现？\n${lines.join('\n')}${more}`)) {
      return;
    }
    this.note.set('');
    this.noteErr.set(false);
    try {
      const ids = rows.map((r) => idOf(r)).filter(Boolean);
      const { message } = await this.api.envelope('POST', W + 'batch-review', { ids, action });
      this.note.set(message || '批量处理完成');
    } catch (e) {
      this.noteErr.set(true);
      this.note.set(errText(e));
    } finally {
      // 成败都以服务端为准回读：被拒的那几笔在服务端仍是 pending
      await this.load();
    }
  }

  /** crud().extra 的落点：提现订单的四个动作（值域见 ORDER_ACTS 的注释） */
  protected override async extra(row: Row, key: string): Promise<void> {
    const call = this.orderCall(row, key);
    if (!call) return;
    if (!confirm(this.orderConfirm(row, key))) return;
    this.note.set('');
    this.noteErr.set(false);
    try {
      const { message } = await call();
      this.note.set(`${this.orderId(row)}：${message || '操作完成'}`);
    } catch (e) {
      // 动作失败是**动作结果**（后端拒绝 / 渠道报错），不是列表加载失败：
      // 进横幅，别把整张表打成错误态（运营还得靠这张表接着处理下一笔）
      this.noteErr.set(true);
      this.note.set(`${this.orderId(row)}：${errText(e)}`);
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
    const who = `「${this.orderId(row)}」（${this.money(row)}）`;
    switch (key) {
      case 'approve':
        return `确认通过提现订单${who}？通过后进入打款环节（要退款只能驳回）。`;
      case 'reject':
        return `确认驳回提现订单${who}？会把平台币退回用户余额并记一条退款流水，不可撤销。`;
      case 'confirm':
        return `确认对提现订单${who}做二次复核？仅「双重审核」开启、且初审人不是自己时可用。`;
      case 'payout':
        return `确认对提现订单${who}执行打款？会真实调用支付渠道，可能产生不可撤销的外部转账。`;
      default:
        return `确认向渠道查询并同步提现订单${who}的打款状态？`;
    }
  }

  /** 订单标识：订单号（列表里人认的是它）带上 hashid（端点要的是它） */
  private orderId(row: Row): string {
    const no = String(row['order_no'] ?? '');
    const id = idOf(row);
    return no ? `${no}/${id}` : id;
  }

  /** 金额一律 dash(原样字符串)：DECIMAL 读回来就是精确的十进制文本，不经 Number */
  private money(row: Row): string {
    return `${dash(row['platform_amount'])} 平台币 → ${dash(row['fiat_amount'])} ${dash(row['currency'])}`;
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
