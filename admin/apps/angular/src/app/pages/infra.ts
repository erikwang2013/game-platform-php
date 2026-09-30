/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, computed, signal } from '@angular/core';
import { Page, Row } from '../core/api.service';
import { Crud, CrudPage, Field } from '../core/crud';
import { idOf } from '../core/render';
import { errText } from '../core/util';
import { Pager, StateBlock, Tabs } from '../components/ui';
import { Table } from '../components/table';
import { FormModal } from '../components/form-modal';

const I = '/admin/v1/';

/**
 * 字段真值 = CdnProviderController::create/update 的 validator。
 * provider 是**唯一键**：create/update 都先查重，撞了回 422「厂商 X 已存在」⇒ 一个厂商一行，两处都不放开。
 * config 是加密存的凭据 JSON：list 明确 unset 它（不回传）⇒ 编辑态必然是空的，
 * 而 update 只在「非空」时才写 ⇒ 留空必须 = 不修改，否则改个名字就把正在用的凭据清成 null 了。
 * status 是 create 的必填项（required|in:0,1）⇒ 表单里得有它（switch 新建恒发）；行内启停走专用 POST toggle。
 */
const CDN_PROVIDERS = ['cloudflare', 'cloudfront', 'aliyun', 'tencent', 'huawei'];

const CDN_FIELDS: Field[] = [
  { name: 'name', label: '显示名称', type: 'text', required: true, placeholder: '最长 50' },
  {
    name: 'provider',
    label: '厂商',
    type: 'select',
    required: true,
    options: CDN_PROVIDERS.map((v) => ({ value: v, label: v })),
  },
  { name: 'status', label: '状态（新建必填）', type: 'switch', required: true },
  { name: 'sort', label: '排序', type: 'number', placeholder: '越小越靠前' },
  {
    name: 'config',
    label: '凭据配置（JSON）',
    type: 'textarea',
    full: true,
    keepIfEmpty: true,
    placeholder: '{"bucket":"...","access_key":"..."}；列表不回传原值，留空 = 不修改',
  },
];

/**
 * 字段真值 = CountryConfigController::create/update 的 validator。
 * country_code 只在 create（update 白名单里没有，且是唯一键）⇒ createOnly。
 * payment_methods / withdraw_methods 是 JSON 文本，两种历史格式都吃
 * （旧数组 ["stripe"]、新规则对象 {"stripe":{"min":..,"enabled":true}}，见 CountryConfig::methodNames）。
 * min_deposit 是 DECIMAL(18,4) 金额 ⇒ 用 text 不透 JS Number，别改成 number。
 * status 走本模块 toggle 端点（行内「启用/停用」），create 恒为 1 不可指定 ⇒ 表单里不摆开关。
 */
const COUNTRY_FIELDS: Field[] = [
  {
    name: 'country_code',
    label: '国家代码',
    type: 'text',
    required: true,
    createOnly: true,
    placeholder: 'ISO 3166-1 alpha-2，如 CN',
  },
  { name: 'currency', label: '货币代码', type: 'text', required: true, placeholder: 'ISO 4217，如 CNY' },
  {
    name: 'payment_methods',
    label: '支付方式（JSON）',
    type: 'textarea',
    keepIfEmpty: true,
    placeholder: '["stripe","paypal"] 或 {"stripe":{"enabled":true}}',
  },
  {
    name: 'withdraw_methods',
    label: '提现方式（JSON）',
    type: 'textarea',
    keepIfEmpty: true,
    placeholder: '["paypal","bank","crypto"]',
  },
  {
    name: 'min_deposit',
    label: '最低充值额',
    type: 'text',
    keepIfEmpty: true,
    placeholder: '十进制金额，如 10.0000；留空 = 不改（新建默认 1.0000）',
  },
];

@Component({
  selector: 'app-infra',
  imports: [StateBlock, Table, Pager, Tabs, FormModal],
  template: `
    <div class="page-head">
      <h1>基础设施</h1>
      <span class="sub">CDN 厂商 / 国家配置</span>
      <div class="spacer"></div>
      <button class="btn" (click)="load()">刷新</button>
      @if (writable()) {
        <button class="btn btn-primary" (click)="openCreate()">+ 新建</button>
      }
    </div>

    <ui-tabs [tabs]="tabs" [active]="tab()" (pick)="pick($event)" />

    @if (note()) {
      <div [class]="noteErr() ? 'alert' : 'notice'">{{ note() }}</div>
    }

    <ui-state [loading]="loading()" [error]="error()" [empty]="!rows().length">
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
    </ui-state>

    @if (rows().length) {
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
export class Infra extends CrudPage {
  protected readonly tabs = [
    { key: 'cdn', label: 'CDN 厂商' },
    { key: 'country', label: '国家配置' },
  ];
  protected readonly tab = signal('cdn');
  /** 动作回执（服务端 message / 失败原因）：就地显示，不把列表打成错误态 */
  protected readonly note = signal('');
  protected readonly noteErr = signal(false);

  private readonly paths: Record<string, string> = {
    cdn: I + 'cdn/provider/list',
    country: I + 'country/config/list',
  };

  /** CDN 列（config 从不回传，别摆出来占一列）；国家配置沿用自动推导的列 */
  protected readonly heads = computed((): Record<string, string> => {
    if (this.tab() !== 'cdn') return {};
    return {
      name: '显示名称',
      provider: '厂商',
      status: '状态(0禁用/1启用)',
      sort: '排序',
      created_at: '创建时间',
      updated_at: '更新时间',
    };
  });

  protected override crud(): Crud | null {
    if (this.tab() === 'cdn') {
      return {
        noun: 'CDN 厂商',
        fields: CDN_FIELDS,
        statused: true,
        label: (row) => String(row['name'] ?? idOf(row)),
        ends: {
          create: I + 'cdn/provider/create',
          update: (id) => I + 'cdn/provider/' + id,
          remove: (id) => I + 'cdn/provider/' + id,
          toggle: I + 'cdn/provider/toggle',
        },
        // 连通测试是外部副作用（HeadBucket 真连厂商），但**只读**、可重试、无参数可配
        // ⇒ 走底座的行内动作 + 就地回执即可，不额外做二次确认
        extra: [{ key: 'test', label: '连通测试' }],
      };
    }
    if (this.tab() !== 'country') return null;
    return {
      noun: '国家配置',
      fields: COUNTRY_FIELDS,
      statused: true,
      label: (row) => String(row['country_code'] ?? idOf(row)),
      ends: {
        create: I + 'country/config/create',
        update: (id) => I + 'country/config/' + id,
        remove: (id) => I + 'country/config/' + id,
        // 国家配置有专用状态端点：POST toggle {id,status}
        toggle: I + 'country/config/toggle',
      },
    };
  }

  protected pick(key: string): void {
    this.tab.set(key);
    this.page.set(1);
    this.note.set('');
    void this.load();
  }

  protected override fetch(): Promise<Page<Row>> {
    const url = this.paths[this.tab()] ?? this.paths['cdn']!;
    // keyword 不再发：两个端点都只吃 page/limit，原先那个「查询」框筛什么都不影响结果
    return this.api.list<Row>(url, { page: this.page(), page_size: this.pageSize });
  }

  /**
   * 连通测试：POST /cdn/provider/test {id}，结果（成功 '连通正常' / 失败是 422 的 message）**就地显示**。
   * 失败走横幅而不是列表级 error —— 探不通是这台厂商的**测试结论**，不是「列表加载失败」，
   * 把表格打成错误态反而看不到别家厂商了。
   */
  protected override async extra(row: Row, key: string): Promise<void> {
    if (key !== 'test') return;
    const id = idOf(row);
    if (!id) return;
    const who = String(row['name'] ?? id);
    this.note.set(`正在测试「${who}」…`);
    this.noteErr.set(false);
    try {
      const { message } = await this.api.envelope('POST', I + 'cdn/provider/test', { id });
      this.note.set(`「${who}」：${message || '连通正常'}`);
    } catch (e) {
      this.noteErr.set(true);
      this.note.set(`「${who}」连通测试失败：${errText(e)}`);
    }
  }
}
