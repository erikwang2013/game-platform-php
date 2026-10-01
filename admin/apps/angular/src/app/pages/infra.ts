/* Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz */
import { Component, computed, signal } from '@angular/core';
import { Page, Row } from '../core/api.service';
import { Crud, CrudPage, Field } from '../core/crud';
import { T } from '../core/i18n/i18n';
import { idOf } from '../core/render';
import { enabledLabel, errText } from '../core/util';
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
  { name: 'name', label: 'cdn.name', type: 'text', required: true, placeholder: 'cdn.name_hint' },
  {
    name: 'provider',
    label: 'cdn.provider',
    type: 'select',
    required: true,
    // 选项名就用后端枚举原文（cloudflare…）：译了反而与库里的值对不上
    options: CDN_PROVIDERS.map((v) => ({ value: v, label: v })),
  },
  { name: 'status', label: 'cdn.status_required', type: 'switch', required: true },
  { name: 'sort', label: 'cdn.sort', type: 'number', placeholder: 'cdn.sort_hint' },
  {
    name: 'config',
    label: 'cdn.config',
    type: 'textarea',
    full: true,
    keepIfEmpty: true,
    hint: 'cdn.config_hint',
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
    label: 'country_config.country_code',
    type: 'text',
    required: true,
    createOnly: true,
    placeholder: 'country_config.country_code_hint',
  },
  {
    name: 'currency',
    label: 'country_config.currency',
    type: 'text',
    required: true,
    placeholder: 'country_config.currency_hint',
  },
  {
    name: 'payment_methods',
    label: 'country_config.payment_methods',
    type: 'textarea',
    keepIfEmpty: true,
    placeholder: 'country_config.payment_methods_hint',
  },
  {
    name: 'withdraw_methods',
    label: 'country_config.withdraw_methods',
    type: 'textarea',
    keepIfEmpty: true,
    placeholder: 'country_config.withdraw_methods_hint',
  },
  {
    name: 'min_deposit',
    label: 'country_config.min_deposit',
    type: 'text',
    keepIfEmpty: true,
    hint: 'country_config.min_deposit_hint',
  },
];

@Component({
  selector: 'app-infra',
  imports: [StateBlock, Table, Pager, Tabs, FormModal, T],
  template: `
    <div class="page-head">
      <h1>{{ 'infra.title' | t }}</h1>
      <span class="sub">{{ 'infra.subtitle' | t }}</span>
      <div class="spacer"></div>
      <button class="btn" (click)="load()">{{ 'app.refresh' | t }}</button>
      @if (writable()) {
        <button class="btn btn-primary" (click)="openCreate()">+ {{ 'app.create' | t }}</button>
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

    @if (paged() && rows().length) {
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
    { key: 'cdn', label: 'cdn.title' },
    { key: 'country', label: 'country_config.title' },
  ];
  protected readonly tab = signal('cdn');

  /**
   * 只有国家配置是 page+limit 分页的：/cdn/provider/list 是 `CdnProvider::orderBy('sort')->get()`
   * （整表、无 total，且被券/支付等表单当厂商下拉复用）⇒ 给它挂分页器就是给出一个假第 2 页。
   */
  protected readonly paged = computed(() => this.tab() !== 'cdn');

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
      name: 'cdn.name',
      provider: 'cdn.provider',
      // 值列改指 status_label（原文案是「状态(0禁用/1启用)」= 把数据库编码当标签，已改平）
      status_label: 'cdn.head.status',
      sort: 'cdn.sort',
      created_at: 'cdn.head.created',
      updated_at: 'cdn.head.updated',
    };
  });

  protected override crud(): Crud | null {
    if (this.tab() === 'cdn') {
      return {
        noun: 'cdn.noun',
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
        extra: [{ key: 'test', label: 'cdn.test' }],
      };
    }
    if (this.tab() !== 'country') return null;
    return {
      noun: 'country_config.noun',
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

  protected override async fetch(): Promise<Page<Row>> {
    const tab = this.tab();
    const url = this.paths[tab] ?? this.paths['cdn']!;
    // keyword 不再发：两个端点都只吃 page/limit，原先那个「查询」框筛什么都不影响结果
    const res = await this.api.list<Row>(url, { page: this.page(), page_size: this.pageSize });
    // 只有 CDN 表有显式列，status 是 TINYINT 0/1（`game_cdn_provider.status`）⇒ 摊平一列文案。
    // 国家配置**是自动推列的**（heads() 给它返回 {}）——多塞一个字段就会多出一列，
    // 那页的 status 也是 0/1，但它的列名是后端字段名，这是另一个缺陷，不在本批范围。
    if (tab !== 'cdn') return res;
    return {
      ...res,
      list: (res.list ?? []).map((r) => ({ ...r, status_label: enabledLabel(r['status']) })),
    };
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
    this.note.set(this.i18n.t('cdn.testing', { who }));
    this.noteErr.set(false);
    try {
      const { message } = await this.api.envelope('POST', I + 'cdn/provider/test', { id });
      this.note.set(
        this.i18n.t('cdn.test_ok', { who, message: message || this.i18n.t('cdn.test_success') }),
      );
    } catch (e) {
      this.noteErr.set(true);
      this.note.set(this.i18n.t('cdn.test_fail', { who, error: errText(e) }));
    }
  }
}
